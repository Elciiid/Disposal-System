<?php
// Include shared helpers
require_once __DIR__ . '/auth_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// COOKIE-BASED AUTH (Works on Vercel Serverless)
// ============================================================
// On Vercel, PHP sessions don't persist between requests because
// each request runs in a new isolated container. Instead, we use
// a cookie token to identify the user, then load their data
// from the database on every request.

$isAuthenticated = false;

// Method 1: Check PHP Session (works on XAMPP)
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $isAuthenticated = true;
}

// Method 2: Check auth_token cookie (works on Vercel)
if (!$isAuthenticated && !empty($_COOKIE['auth_token'])) {
    $tokenData = json_decode(base64_decode($_COOKIE['auth_token']), true);
    
    if ($tokenData && isset($tokenData['user_id']) && isset($tokenData['username'])) {
        // Load user from database using the token
        require_once __DIR__ . '/../connection/database.php';
        try {
            $stmt = $conn->prepare("
                SELECT u.UserID as user_id, u.Username as username, u.FullName as full_name, r.RoleName as role_name,
                       u.RoleID as role_id, u.AreaID as area_id, u.PhaseID as phase_id, u.EmployeeID as employee_id
                FROM wst_Users u
                LEFT JOIN wst_Roles r ON u.RoleID = r.RoleID
                WHERE u.UserID = ? AND u.Username = ?
            ");
            $stmt->execute([$tokenData['user_id'], $tokenData['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Rebuild session from database
                bootstrapSession($user, [
                    'PositionTitle' => $user['role_name'],
                    'FirstName'     => $user['full_name'],
                    'EmployeeID'    => $user['employee_id']
                ]);
                $isAuthenticated = true;
            }
        } catch (PDOException $e) {
            // DB error — can't authenticate
            error_log("Auth cookie DB error: " . $e->getMessage());
        }
    }
}

// If still not authenticated, redirect to login
if (!$isAuthenticated) {
    // Clear any stale cookies
    setcookie('auth_token', '', time() - 3600, '/');
    header("Location: ../pages/login.php");
    exit();
}

// Default "No Face" Avatar (SVG Data URI)
if (!defined('DEFAULT_AVATAR_URL')) {
    define('DEFAULT_AVATAR_URL', "data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2394a3b8%22 style=%22background:%23e2e8f0; border-radius: 50%;%22%3e%3cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3e%3c/svg%3e");
}
?>
