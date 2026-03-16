<?php
/**
 * ============================================================
 * Approval Workflow — Core Module
 * Production Waste Management System
 * ============================================================
 *
 * STATE MACHINE
 * ─────────────
 * Every WasteLog starts with CurrentStep = 1, ApprovalStatus = 'Pending'.
 *
 * CurrentStep  │ Who Must Approve           │ Scope
 * ─────────────┼────────────────────────────┼─────────────
 *      1       │ Manager (all types)        │ Same Phase
 *      2       │ Internal Security          │ All Phases
 *      0       │ (fully approved)           │ —
 *
 * On approval of step N:
 *   → StepN_ApprovedBy = userId, StepN_ApprovedAt = NOW()
 *   → CurrentStep = N + 1  (or 0 if N == 2, and ApprovalStatus = 'Approved')
 *
 * On rejection at any step:
 *   → ApprovalStatus = 'Rejected', CurrentStep stays (audit trail).
 */

// ── Step Configuration ──────────────────────────────────────
// 'roles' = array of wst_Roles.RoleName values that can approve this step.
// 'label' = human-readable label for this step.
// 'scope' = 'phase' (must match PhaseID) or 'global' (any phase).

define('APPROVAL_STEPS', [
    1 => [
        'roles' => ['Manager', 'Production Manager A', 'Production Manager B', 'Production Manager C', 'Assistant Product Manager'],
        'label' => 'Manager',
        'scope' => 'phase',
    ],
    2 => [
        'roles' => ['Internal Security'],
        'label' => 'Internal Security',
        'scope' => 'global',
    ],
]);


/**
 * Checks if a user has authority for a given step based on permissions.
 */
function userHasPermissionForStep($conn, int $step): bool
{
    require_once __DIR__ . '/../auth/auth_helpers.php';
    $permKey = "approve_step_" . $step;
    return hasPermission($conn, $permKey);
}

/**
 * Returns an array of all step numbers the current user is authorized to approve.
 */
function getAuthorizedStepsForUser($conn): array
{
    $authorizedSteps = [];
    foreach (APPROVAL_STEPS as $step => $config) {
        if (userHasPermissionForStep($conn, $step)) {
            $authorizedSteps[] = $step;
        }
    }
    return $authorizedSteps;
}

/**
 * Returns the first step number the current user is authorized to approve (for backward compatibility/UI labels).
 */
function getStepForUser($conn): array
{
    $steps = getAuthorizedStepsForUser($conn);
    if (empty($steps)) return [null, null];
    $firstStep = $steps[0];
    return [$firstStep, APPROVAL_STEPS[$firstStep]];
}

/**
 * Returns all WasteLog rows that the current user is allowed to approve.
 *
 * @param  PDO    $conn          Database connection
 * @param  int    $userPhaseId   The logged-in user's PhaseID
 * @param  string $userRoleName  (Deprecated)
 * @return array                 Array of matching WasteLog rows
 */
function getPendingRequests(PDO $conn, $userPhaseId, ?string $userRoleName = null, array $filters = []): array
{
    // 1. Determine all steps this user is authorized for based on permissions
    $authorizedSteps = getAuthorizedStepsForUser($conn);

    // No permission for any approval step → return nothing
    if (empty($authorizedSteps)) {
        return [];
    }

    // Check if limit is set, inject TOP clause
    $topClause = "";
    if (isset($filters['limit']) && is_numeric($filters['limit'])) {
        $topClause = "TOP " . intval($filters['limit']) . " ";
    }

    // 2. Build the query
    $sql = "
        SELECT {$topClause}w.LogID, w.LogDate, w.TypeID, w.PhaseID, w.AreaID,
               w.ShiftID, w.CategoryID, w.DescriptionID,
               w.PCS, w.KG, w.Reason, w.SubmittedBy,
               w.CurrentStep, w.ApprovalStatus,
               w.Step1ApprovedBy, w.Step1ApprovedAt,
               w.Step2ApprovedBy, w.Step2ApprovedAt,
               w.Step3ApprovedBy, w.Step3ApprovedAt,
               w.Step4ApprovedBy, w.Step4ApprovedAt,
               w.Step5ApprovedBy, w.Step5ApprovedAt,
               w.OtherTypeRemark,
               p.PhaseName,
               t.TypeName,
               a.AreaName,
               s.ShiftName,
               c.CategoryName,
               d.DescriptionName,
               COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(m.FirstName, '') + ' ' + ISNULL(m.LastName, ''))), ''), NULLIF(LTRIM(RTRIM(lu.full_name COLLATE DATABASE_DEFAULT)), ''), w.SubmittedBy COLLATE DATABASE_DEFAULT) AS SubmitterName,
               COALESCE(NULLIF(LTRIM(RTRIM(m.EmployeeID COLLATE DATABASE_DEFAULT)), ''), w.SubmittedBy COLLATE DATABASE_DEFAULT) AS SubmitterEmployeeID,
               COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(au.FirstName, '') + ' ' + ISNULL(au.LastName, ''))), ''), NULLIF(LTRIM(RTRIM(alu.full_name COLLATE DATABASE_DEFAULT)), ''), (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step5ApprovedBy, w.Step4ApprovedBy, w.Step3ApprovedBy, w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT) AS ApproverName,
               COALESCE(NULLIF(LTRIM(RTRIM(au.EmployeeID COLLATE DATABASE_DEFAULT)), ''), (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step5ApprovedBy, w.Step4ApprovedBy, w.Step3ApprovedBy, w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT) AS ApproverEmployeeID,
               COALESCE(NULLIF(LTRIM(RTRIM(au.BiometricsID COLLATE DATABASE_DEFAULT)), ''), (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step5ApprovedBy, w.Step4ApprovedBy, w.Step3ApprovedBy, w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT) AS ApproverBiometricsID
        FROM wst_Logs w
        LEFT JOIN wst_Phases p        ON w.PhaseID       = p.PhaseID
        LEFT JOIN wst_LogTypes t      ON w.TypeID        = t.TypeID
        LEFT JOIN wst_Areas a         ON w.AreaID        = a.AreaID
        LEFT JOIN wst_Shifts s        ON w.ShiftID       = s.ShiftID
        LEFT JOIN wst_PCategories c   ON w.CategoryID    = c.CategoryID
        LEFT JOIN wst_PDescriptions d  ON w.DescriptionID = d.DescriptionID
        LEFT JOIN LRNPH_E.dbo.lrn_master_list m
            ON w.SubmittedBy COLLATE DATABASE_DEFAULT = m.BiometricsID COLLATE DATABASE_DEFAULT
            AND m.IsActive = 1
        LEFT JOIN LRNPH.dbo.lrnph_users lu
            ON w.SubmittedBy COLLATE DATABASE_DEFAULT = lu.username COLLATE DATABASE_DEFAULT
        LEFT JOIN LRNPH_E.dbo.lrn_master_list au 
            ON (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step5ApprovedBy, w.Step4ApprovedBy, w.Step3ApprovedBy, w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT = au.BiometricsID COLLATE DATABASE_DEFAULT
        LEFT JOIN LRNPH.dbo.lrnph_users alu 
            ON (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step5ApprovedBy, w.Step4ApprovedBy, w.Step3ApprovedBy, w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT = alu.username COLLATE DATABASE_DEFAULT
        WHERE w.CurrentStep IN (" . implode(',', array_map('intval', $authorizedSteps)) . ")
          AND w.ApprovalStatus = 'Pending'
    ";

    $params = [];

    // 3. Phase-bound steps filters
    // If ANY of the authorized steps are phase-bound, we must handle phase filtering carefully.
    // However, to keep it simple and consistent with previous "Global" logic for unassigned roles:
    // We only apply phase filter if the user HAS an assigned phase.
    if (!empty($userPhaseId)) {
        // Collect steps that are phase-bound
        $phaseBoundSteps = [];
        foreach ($authorizedSteps as $sNum) {
            if (APPROVAL_STEPS[$sNum]['scope'] === 'phase') {
                $phaseBoundSteps[] = $sNum;
            }
        }
        
        if (!empty($phaseBoundSteps)) {
            // If the current step is one of the phase-bound ones, it must match the user's phase.
            // (Global steps remain visible for all phases).
            $phaseInClause = implode(',', array_map('intval', $phaseBoundSteps));
            $sql .= " AND (w.CurrentStep NOT IN ($phaseInClause) OR w.PhaseID = :phaseId)";
            $params[':phaseId'] = $userPhaseId;
        }
    }

    // 4. Date and Limit Filtering
    if (!empty($filters['startDate'])) {
        $sql .= " AND w.LogDate >= :startDate";
        $params[':startDate'] = $filters['startDate'];
    }
    if (!empty($filters['endDate'])) {
        $sql .= " AND w.LogDate <= :endDate";
        $pe = $filters['endDate'];
        if (strlen($pe) == 10) {
            $pe .= ' 23:59:59';
        }
        $params[':endDate'] = $pe;
    }

    // Additional Filters (Phase is already handled by step scope)
    if (!empty($filters['shiftId'])) {
        $sql .= " AND w.ShiftID = :shiftId";
        $params[':shiftId'] = $filters['shiftId'];
    }
    if (!empty($filters['areaId'])) {
        $sql .= " AND w.AreaID = :areaId";
        $params[':areaId'] = $filters['areaId'];
    }
    if (!empty($filters['typeId'])) {
        $sql .= " AND w.TypeID = :typeId";
        $params[':typeId'] = $filters['typeId'];
    }
    if (!empty($filters['categoryId'])) {
        $sql .= " AND w.CategoryID = :categoryId";
        $params[':categoryId'] = $filters['categoryId'];
    }

    $sql .= " ORDER BY w.LogDate DESC, w.LogID DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Validates authority and advances a WasteLog to the next approval step.
 *
 * @param  PDO    $conn          Database connection
 * @param  int    $requestId     LogID to approve
 * @param  string $userId        Username / BiometricsID of the approver
 * @param  int    $userPhaseId   The approver's PhaseID
 * @return array                 ['success' => bool, 'message' => string]
 */
function approveRequest(PDO $conn, int $requestId, string $userId, $userPhaseId): array
{
    return processApprovalAction($conn, $requestId, $userId, $userPhaseId, 'approve');
}


// ─────────────────────────────────────────────────────────────
//  rejectRequest
// ─────────────────────────────────────────────────────────────
/**
 * Validates authority and rejects the WasteLog at its current step.
 *
 * @param  PDO    $conn          Database connection
 * @param  int    $requestId     LogID to reject
 * @param  string $userId        Username / BiometricsID of the rejector
 * @param  int    $userPhaseId   The rejector's PhaseID
 * @return array                 ['success' => bool, 'message' => string]
 */
function rejectRequest(PDO $conn, int $requestId, string $userId, $userPhaseId, string $reason = ''): array
{
    return processApprovalAction($conn, $requestId, $userId, $userPhaseId, 'reject', $reason);
}


// ─────────────────────────────────────────────────────────────
//  processApprovalAction  (private helper)
// ─────────────────────────────────────────────────────────────
/**
 * Core state-machine logic shared by approve and reject.
 *
 * Security validations:
 *   1. Request must exist and be 'Pending'.
 *   2. User must have the permission (e.g. approve_step_1) for the current step.
 *   3. For phase-bound steps, user's PhaseID must match the request's PhaseID.
 *
 * @param  PDO    $conn
 * @param  int    $requestId
 * @param  string $userId
 * @param  int    $userPhaseId
 * @param  string $action        'approve' or 'reject'
 * @return array
 */
function processApprovalAction(PDO $conn, int $requestId, string $userId, $userPhaseId, string $action, string $reason = ''): array
{
    // 1. Fetch the current state of the request
    $stmt = $conn->prepare("
        SELECT LogID, PhaseID, CurrentStep, ApprovalStatus
        FROM wst_Logs
        WHERE LogID = :id
    ");
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        return ['success' => false, 'message' => 'Request not found.'];
    }

    if ($request['ApprovalStatus'] !== 'Pending') {
        return ['success' => false, 'message' => 'This request has already been ' . strtolower($request['ApprovalStatus']) . '.'];
    }

    $currentStep = (int)$request['CurrentStep'];

    if ($currentStep < 1 || $currentStep > 2) {
        return ['success' => false, 'message' => 'Request is not in an approvable state (step ' . $currentStep . ').'];
    }

    // 2. Validate user has permission for this step
    $stepConfig = APPROVAL_STEPS[$currentStep];

    if (!userHasPermissionForStep($conn, $currentStep)) {
        return [
            'success' => false,
            'message' => "Access denied. You do not have the 'approve_step_$currentStep' permission required for this action."
        ];
    }

    // 3. For phase-bound steps, validate PhaseID match (Skip if user has no phase assigned, e.g. Super Admin)
    if ($stepConfig['scope'] === 'phase' && !empty($userPhaseId)) {
        if ((int)$userPhaseId !== (int)$request['PhaseID']) {
            return [
                'success' => false,
                'message' => "Access denied. You are assigned to Phase $userPhaseId but this request belongs to Phase {$request['PhaseID']}."
            ];
        }
    }

    // ── All checks passed — update the database ──

    if ($action === 'reject') {
        // Rejection: stamp this step and mark the request as Rejected
        $sql = "
            UPDATE wst_Logs
            SET Step{$currentStep}ApprovedBy = :userId,
                Step{$currentStep}ApprovedAt = GETDATE(),
                ApprovalStatus = 'Rejected',
                RejectionReason = :reason,
                RejectedBy = :rejectedBy
            WHERE LogID = :id
              AND CurrentStep = :step
              AND ApprovalStatus = 'Pending'
        ";
        $params = [
            ':userId'     => $userId,
            ':id'         => $requestId,
            ':step'       => $currentStep,
            ':reason'     => $reason,
            ':rejectedBy' => $userId,
        ];

        $update = $conn->prepare($sql);
        $update->execute($params);

        if ($update->rowCount() === 0) {
            return ['success' => false, 'message' => 'Race condition: request state changed. Please refresh and try again.'];
        }

        return [
            'success' => true,
            'message' => "Entry Declined"
        ];
    }

    // Approval: stamp this step and advance
    $nextStep = ($currentStep === 2) ? 0 : $currentStep + 1;
    $newStatus = ($currentStep === 2) ? 'Approved' : 'Pending';

    $sql = "
        UPDATE wst_Logs
        SET Step{$currentStep}ApprovedBy = :userId,
            Step{$currentStep}ApprovedAt = GETDATE(),
            CurrentStep    = :nextStep,
            ApprovalStatus = :newStatus
        WHERE LogID = :id
          AND CurrentStep = :currentStep
          AND ApprovalStatus = 'Pending'
    ";
    $params = [
        ':userId'      => $userId,
        ':nextStep'    => $nextStep,
        ':newStatus'   => $newStatus,
        ':id'          => $requestId,
        ':currentStep' => $currentStep,
    ];

    $update = $conn->prepare($sql);
    $update->execute($params);

    if ($update->rowCount() === 0) {
        return ['success' => false, 'message' => 'Race condition: request state changed. Please refresh and try again.'];
    }

    $stepLabel = $stepConfig['label'];
    if ($currentStep === 2) {
        return [
            'success' => true,
            'message' => "Request #$requestId fully approved! Final approval by $stepLabel ($userId)."
        ];
    }

    $nextLabel = APPROVAL_STEPS[$nextStep]['label'];
    return [
        'success' => true,
        'message' => "Request #$requestId approved at Step $currentStep ($stepLabel). Now pending Step $nextStep ($nextLabel)."
    ];
}


// ─────────────────────────────────────────────────────────────
//  getApprovalHistory  (utility)
// ─────────────────────────────────────────────────────────────
/**
 * Returns a human-readable approval trail for a given request.
 *
 * @param  array $wasteLog  A single WasteLog row (must include Step*ApprovedBy/At columns)
 * @return array            Array of step records with role, approver, timestamp, and status
 */
function getApprovalHistory(array $wasteLog): array
{
    $history = [];
    $currentStep = (int)($wasteLog['CurrentStep'] ?? 0);
    $status = $wasteLog['ApprovalStatus'] ?? 'Pending';

    foreach (APPROVAL_STEPS as $step => $config) {
        $approvedBy = $wasteLog["Step{$step}ApprovedBy"] ?? null;
        $approvedAt = $wasteLog["Step{$step}ApprovedAt"] ?? null;

        if ($approvedBy) {
            // This step was signed
            $stepStatus = ($status === 'Rejected' && $step === $currentStep) ? 'Rejected' : 'Approved';
        } elseif ($step === $currentStep && $status === 'Pending') {
            $stepStatus = 'Awaiting';
        } else {
            $stepStatus = 'Pending';
        }

        $history[] = [
            'step'       => $step,
            'role'       => $config['label'],
            'scope'      => $config['scope'],
            'approvedBy' => $approvedBy,
            'approvedAt' => $approvedAt,
            'status'     => $stepStatus,
        ];
    }

    return $history;
}
/**
 * DRY: Extracts the shared logic for fetching pending approval data used in headers/sidebars.
 *
 * @param  PDO $conn
 * @return array ['pendingLogs' => array, 'pendingCount' => int, 'latestPendingLogs' => array]
 */
function getApprovalContext(PDO $conn): array
{
    $role  = $_SESSION['wst_role_name'] ?? null;
    $phase = $_SESSION['wst_phase_id'] ?? null;
    
    $pendingLogs = [];
    if ($role) {
        try {
            $pendingLogs = getPendingRequests($conn, $phase);
        } catch (PDOException $e) {
            // Log error if needed, for now fail silently
        }
    }

    return [
        'pendingLogs'       => $pendingLogs,
        'pendingCount'      => count($pendingLogs),
        'latestPendingLogs' => array_slice($pendingLogs, 0, 5)
    ];
}
?>
