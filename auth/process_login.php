<?php
session_start();
require_once '../connection/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both username and password.";
        header("Location: ../pages/login.php");
        exit();
    }

    try {
        // Query the LRNPH database but join it with our local wst_Users to grab Phase/Area/Role
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, u.password, u.full_name, u.role,
                   w.RoleID, r.RoleName, w.AreaID, w.PhaseID
            FROM LRNPH.dbo.lrnph_users u
            LEFT JOIN wst_Users w ON u.username COLLATE DATABASE_DEFAULT = w.Username COLLATE DATABASE_DEFAULT
            LEFT JOIN wst_Roles r ON w.RoleID = r.RoleID
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Fetch extra details from the master list using the username (BiometricsID)
            $stmtMaster = $conn->prepare("SELECT [FirstName], [LastName], [PositionTitle], [EmployeeID], [Department] FROM LRNPH_E.dbo.lrn_master_list WHERE BiometricsID = ? AND IsActive = 1");
            $stmtMaster->execute([$user['username']]);
            $masterInfo = $stmtMaster->fetch(PDO::FETCH_ASSOC);
            
            // Login successful — use shared bootstrap (DRY)
            require_once __DIR__ . '/auth_helpers.php';
            bootstrapSession($user, $masterInfo);
            
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
