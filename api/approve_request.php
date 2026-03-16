<?php
/**
 * Approve / Reject Request Endpoint
 * POST body: { requestId: int, action: 'approve'|'reject' }
 *
 * Response: JSON { success: bool, message: string }
 */
session_start();
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/approval_workflow.php';

header('Content-Type: application/json');

// ── Authentication ──────────────────────────────────────────
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// ── Only allow POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// ── Read & validate input ───────────────────────────────────
$requestId = isset($_POST['requestId']) ? (int)$_POST['requestId'] : 0;
$action    = $_POST['action'] ?? '';
$reason    = trim($_POST['reason'] ?? '');

if ($requestId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing requestId.']);
    exit;
}

if (!in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Invalid action '$action'. Must be 'approve' or 'reject'."]);
    exit;
}

// ── User identity from session ──────────────────────────────
$userId       = $_SESSION['username'];        // BiometricsID
$userRoleName = $_SESSION['wst_role_name'] ?? null;
$userPhaseId  = $_SESSION['wst_phase_id']  ?? null;

if (!$userRoleName) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your account has no assigned role in the Production Waste system.']);
    exit;
}

// ── Execute action ──────────────────────────────────────────
try {
    if ($action === 'approve') {
        $result = approveRequest($conn, $requestId, $userId, $userPhaseId);
    } else {
        $result = rejectRequest($conn, $requestId, $userId, $userPhaseId, $reason);
    }

    $httpCode = $result['success'] ? 200 : 403;
    http_response_code($httpCode);
    echo json_encode($result);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
