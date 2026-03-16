<?php
/**
 * auth/auth_helpers.php
 * Shared helpers for authentication and session management.
 */

require_once __DIR__ . '/../utils/photo_helper.php';

/**
 * DRY: Centralized session bootstrapping from a user DB row + optional master list row.
 * Called by both auth.php (auto-fill) and process_login.php (login).
 */
function bootstrapSession(array $user, $masterInfo): void
{
    $position  = $masterInfo ? ($masterInfo['PositionTitle'] ?? 'Employee') : 'Employee';
    $firstName = $masterInfo ? ($masterInfo['FirstName'] ?? '') : '';
    $lastName  = $masterInfo ? ($masterInfo['LastName'] ?? '') : '';
    $fullName  = trim($firstName . ' ' . $lastName);
    if (empty($fullName)) {
        $fullName = $_SESSION['fullname'] ?? ($user['full_name'] ?? 'User');
    }

    $_SESSION['user_id']     = $user['user_id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['employee_id'] = $masterInfo ? ($masterInfo['EmployeeID'] ?? '') : '';
    $_SESSION['department']  = $masterInfo ? ($masterInfo['Department'] ?? '') : '';
    $_SESSION['full_name']   = $fullName;
    $_SESSION['role']        = $user['role'] ?? 'User';
    $_SESSION['position']    = $position;

    // Production System Roles
    $_SESSION['wst_role_id']   = $user['RoleID'] ?? null;
    $_SESSION['wst_role_name'] = $user['RoleName'] ?? null;
    $_SESSION['wst_area_id']   = $user['AreaID'] ?? null;
    $_SESSION['wst_phase_id']  = $user['PhaseID'] ?? null;

    $_SESSION['avatar'] = getEmployeePhotoUrl($user['username']);
}

/**
 * Returns a standardized array representing the current user from session.
 */
function getCurrentUser() {
    // Default avatar
    $defaultAvatar = "data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2394a3b8%22 style=%22background:%23e2e8f0; border-radius: 50%;%22%3e%3cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3e%3c/svg%3e";

    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'employee_id' => $_SESSION['employee_id'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'User',
        'department' => $_SESSION['department'] ?? '',
        'position' => $_SESSION['position'] ?? 'Employee',
        'avatar' => $_SESSION['avatar'] ?? $defaultAvatar,
        'wst_role_id' => $_SESSION['wst_role_id'] ?? null,
        'wst_role_name' => $_SESSION['wst_role_name'] ?? null,
        'wst_area_id' => $_SESSION['wst_area_id'] ?? null,
        'wst_phase_id' => $_SESSION['wst_phase_id'] ?? null
    ];
}

/**
 * Checks if the current user's role has a specific permission.
 */
function hasPermission($conn, $permissionKey) {
    $roleId = $_SESSION['wst_role_id'] ?? null;
    if (!$roleId) return false;

    $sql = "
        SELECT 1 
        FROM wst_RolePermissions rp
        JOIN wst_Permissions p ON rp.PermissionID = p.PermissionID
        WHERE rp.RoleID = :roleId AND p.PermissionKey = :key
    ";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute(['roleId' => $roleId, 'key' => $permissionKey]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Helper to check if user has access to Settings and Approvals.
 */
function hasSettingsAccess($conn, $username, $userRoleName = null) {
    if (empty($username)) return false;

    // 1. Check for explicit 'access_settings' permission
    if (hasPermission($conn, 'access_settings')) return true;
    
    // 2. Fallback check for IT Department (Legacy support or global admin)
    $sql = "
        SELECT 1 
        FROM LRNPH_E.dbo.lr_master_list 
        WHERE BiometricsID = :username 
        AND IsActive = 1 
        AND Department = 'Information Technology Department - LRN'
    ";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute(['username' => $username]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

?>
