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
            SELECT u.UserID as user_id, u.Username as username, u.Password as password, u.FullName as full_name, r.RoleName as role_name,
                   u.RoleID as role_id, u.AreaID as area_id, u.PhaseID as phase_id, u.EmployeeID as employee_id
            FROM wst_Users u
            LEFT JOIN wst_Roles r ON u.RoleID = r.RoleID
            WHERE u.Username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && trim($password) === trim($user['password'])) {
            // Login successful — use shared bootstrap (DRY)
            require_once __DIR__ . '/auth_helpers.php';
            bootstrapSession($user, [
                'PositionTitle' => $user['role_name'],
                'FirstName'     => $user['full_name'],
                'EmployeeID'    => $user['employee_id']
            ]);
            
            error_log("Login Successful for user: $username. Redirecting to dashboard.");
            session_write_close();
            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            // Login failed - be more specific for debugging
            if (!$user) {
                $_SESSION['login_error'] = "User not found in database.";
                error_log("Login Failed: User '$username' not found.");
            } else {
                $_SESSION['login_error'] = "Password mismatch. Please check your credentials.";
                error_log("Login Failed: Password mismatch for user '$username'.");
            }
            session_write_close();
            header("Location: ../pages/login.php?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        // TEMPORARY: Exposing the exact DB error for debugging IT account login failure
        $_SESSION['login_error'] = "Authentication error: " . $e->getMessage();
        session_write_close();
        header("Location: ../pages/login.php?error=db");
        exit();
    }
} else {
    header("Location: ../pages/login.php");
    exit();
}
