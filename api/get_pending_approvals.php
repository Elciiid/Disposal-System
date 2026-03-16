<?php
/**
 * GET Pending Approvals Endpoint
 * Returns waste logs that the currently logged-in user is authorized to approve.
 *
 * Response: JSON { success: bool, data: array, meta: { step, role, scope } }
 */
session_start();
require_once '../connection/database.php';
require_once __DIR__ . '/approval_workflow.php';

header('Content-Type: application/json');

// ── Authentication ──────────────────────────────────────────
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$userRoleName = $_SESSION['wst_role_name'] ?? null;
$userPhaseId  = $_SESSION['wst_phase_id']  ?? null;

if (!$userRoleName) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your account has no assigned role in the Production Waste system.']);
    exit;
}

// ── Fetch ────────────────────────────────────────────────────
try {
    $requests = getPendingRequests($conn, $userPhaseId);

    // Determine which step this user handles based on permissions
    [$stepNumber, $stepConfig] = getStepForUser($conn);

    echo json_encode([
        'success' => true,
        'data'    => $requests,
        'meta'    => [
            'step'  => $stepNumber,
            'role'  => $_SESSION['wst_role_name'] ?? null,
            'label' => $stepConfig['label'] ?? null,
            'scope' => $stepConfig['scope'] ?? null,
            'count' => count($requests),
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
