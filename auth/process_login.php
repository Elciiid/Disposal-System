<?php
session_start();
require_once __DIR__ . '/../connection/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        setcookie('login_error', 'Please enter both username and password.', time() + 60, '/');
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
            // Login successful — set auth cookie (works on serverless!)
            $token = base64_encode(json_encode([
                'user_id'  => $user['user_id'],
                'username' => $user['username'],
                'ts'       => time()
            ]));

            // Set cookie that lasts 24 hours
            setcookie('auth_token', $token, [
                'expires'  => time() + 86400,
                'path'     => '/',
                'secure'   => true,
                'httponly'  => true,
                'samesite'  => 'Lax'
            ]);

            // Also set session for local XAMPP compatibility
            require_once __DIR__ . '/auth_helpers.php';
            bootstrapSession($user, [
                'PositionTitle' => $user['role_name'],
                'FirstName'     => $user['full_name'],
                'EmployeeID'    => $user['employee_id']
            ]);
            session_write_close();

            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            if (!$user) {
                setcookie('login_error', 'User not found in database.', time() + 60, '/');
            } else {
                setcookie('login_error', 'Invalid password. Please try again.', time() + 60, '/');
            }
            header("Location: ../pages/login.php?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        setcookie('login_error', 'Database error: ' . $e->getMessage(), time() + 60, '/');
        header("Location: ../pages/login.php?error=db");
        exit();
    }
} else {
    header("Location: ../pages/login.php");
    exit();
}
