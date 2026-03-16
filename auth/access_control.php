<?php
require_once __DIR__ . '/auth.php';

function requireSupervisorAccess($conn, $user) {
    if (!$user || empty($user['username'])) {
        header("Location: ../pages/login.php");
        exit();
    }

    require_once __DIR__ . '/auth_helpers.php';
    if (!hasPermission($conn, 'access_settings')) {
        $_SESSION['error_msg'] = "Access Denied: You do not have the required privileges to view this page.";
        header("Location: ../pages/index.php");
        exit();
    }
}
?>
