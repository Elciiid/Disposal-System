<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/auth_helpers.php';

$currentUser = getCurrentUser();
if (!hasSettingsAccess($conn, $currentUser['username'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$roleId = $_GET['roleId'] ?? null;
if (!$roleId) {
    echo json_encode(['success' => false, 'error' => 'RoleID is required']);
    exit();
}

try {
    // 1. Fetch all permissions (excluding those marked as Obsolete)
    $allPerms = $conn->query("SELECT PermissionID, PermissionKey, PermissionLabel, Category 
                             FROM wst_Permissions 
                             WHERE PermissionLabel NOT LIKE 'Obsolete%'
                             ORDER BY Category, PermissionLabel")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch assigned permissions for this role
    $assignedStmt = $conn->prepare("SELECT PermissionID FROM wst_RolePermissions WHERE RoleID = ?");
    $assignedStmt->execute([$roleId]);
    $assignedIds = $assignedStmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Merge
    foreach ($allPerms as &$p) {
        $p['assigned'] = in_array($p['PermissionID'], $assignedIds);
    }

    echo json_encode(['success' => true, 'permissions' => $allPerms]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
