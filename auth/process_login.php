<?php
session_start();
require_once __DIR__ . '/../connection/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both username and password.";
        header("Location: ../pages/login.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("
            SELECT u.UserID as user_id, u.Username as username, u.Password as password, u.FullName as full_name, r.RoleName as role,
                   u.RoleID, r.RoleName as wst_role_name, u.AreaID, u.PhaseID, u.EmployeeID
            FROM wst_Users u
            LEFT JOIN wst_Roles r ON u.RoleID = r.RoleID
            WHERE u.Username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            // Login successful — use shared bootstrap (DRY)
            require_once __DIR__ . '/auth_helpers.php';
            bootstrapSession($user, [
                'PositionTitle' => $user['role'],
                'FirstName'     => $user['full_name'],
                'EmployeeID'    => $user['employee_id']
            ]);
            
            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            // Login failed
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: ../pages/login.php");
            exit();
        }
    } catch (PDOException $e) {
        // TEMPORARY: Exposing the exact DB error for debugging IT account login failure
        $_SESSION['login_error'] = "Authentication error: " . $e->getMessage();
        header("Location: ../pages/login.php");
        exit();
    }
} else {
    header("Location: ../pages/login.php");
    exit();
}
