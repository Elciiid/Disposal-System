<?php
// Include shared helpers
require_once __DIR__ . '/auth_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Gatekeeper: If not logged in, boot out to login page
// (Exception: skip if we are in the middle of a login process/auto-fill check)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: ../pages/login.php");
    exit();
}

// 2. Auto-fill session details if coming from another app (like the lrnph portal)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    require_once __DIR__ . '/../connection/database.php';
    try {
            $stmt = $conn->prepare("
                SELECT u.UserID as user_id, u.Username as username, u.FullName as full_name, r.RoleName as role,
                       u.RoleID, r.RoleName, u.AreaID, u.PhaseID, u.EmployeeID
                FROM wst_Users u
                LEFT JOIN wst_Roles r ON u.RoleID = r.RoleID
                WHERE u." . (isset($_SESSION['user_id']) ? "UserID" : "Username") . " = ?
            ");
            $stmt->execute([isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['username']]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Login successful
            bootstrapSession($user, [
                'PositionTitle' => $user['role'],
                'FirstName'     => $user['full_name'],
                'EmployeeID'    => $user['employee_id']
            ]);
        } else {
            // User ID in session but not found in DB? Clear and redirect.
            session_destroy();
            header("Location: ../pages/login.php?error=session_invalid");
            exit();
        }
    } catch (PDOException $e) {
        // If DB is down, we can't safely proceed.
        header("Location: ../pages/login.php?error=db_error");
        exit();
    }
}

// Default "No Face" Avatar (SVG Data URI) - Gray User on Light Gray Background
if (!defined('DEFAULT_AVATAR_URL')) {
    define('DEFAULT_AVATAR_URL', "data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2394a3b8%22 style=%22background:%23e2e8f0; border-radius: 50%;%22%3e%3cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3e%3c/svg%3e");
}
?>
