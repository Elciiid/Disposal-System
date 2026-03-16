<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/auth_helpers.php';

$currentUser = getCurrentUser();
if (!hasSettingsAccess($conn, $currentUser['username'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$roleId      = $_POST['roleId'] ?? null;
$roleName    = trim($_POST['roleName'] ?? '');
$permissions = $_POST['permissions'] ?? []; // Array of PermissionIDs

if (!$roleId) {
    echo json_encode(['success' => false, 'error' => 'RoleID is required']);
    exit();
}

try {
    $conn->beginTransaction();

    // 1. Update role name if provided
    if (!empty($roleName)) {
        // Format to Title Case
        $roleName = ucwords(strtolower($roleName));
        
        // Check for duplicate (excluding self)
        $check = $conn->prepare("SELECT COUNT(*) FROM wst_Roles WHERE RoleName = ? AND RoleID != ?");
        $check->execute([$roleName, $roleId]);
        if ($check->fetchColumn() > 0) throw new Exception("A role with this name already exists");

        $stmt = $conn->prepare("UPDATE wst_Roles SET RoleName = ? WHERE RoleID = ?");
        $stmt->execute([$roleName, $roleId]);
    }

    // 2. Clear existing permissions for this role
    $stmt = $conn->prepare("DELETE FROM wst_RolePermissions WHERE RoleID = ?");
    $stmt->execute([$roleId]);

    // 3. Insert new selections
    if (!empty($permissions)) {
        $insert = $conn->prepare("INSERT INTO wst_RolePermissions (RoleID, PermissionID) VALUES (?, ?)");
        foreach ($permissions as $pId) {
            $insert->execute([$roleId, $pId]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
