<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/access_control.php';
require_once '../utils/functions.php';

$currentUser = getCurrentUser();
require_once '../auth/auth_helpers.php';

// Ensure only authorized users can hit this API
if (!hasPermission($conn, 'access_settings')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access: You lack the required permissions to manage settings.']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_user') {
        $userId   = $_POST['userId']   ?? '';
        $username = trim($_POST['username'] ?? '');
        $roleId   = $_POST['roleId']   ?? '';
        $phaseId  = $_POST['phaseId']  ?: null;
        $areaId   = $_POST['areaId']   ?: null;

        if (empty($username) || empty($roleId)) {
            throw new Exception("Username and Role are required");
        }

        if (!empty($userId)) {
            // Update
            // Also explicitly grab the latest FullName
            $nameSql = $conn->prepare("SELECT FirstName, LastName FROM LRNPH_E.dbo.lrn_master_list WHERE BiometricsID = ? AND IsActive = 1");
            $nameSql->execute([$username]);
            $match = $nameSql->fetch(PDO::FETCH_ASSOC);
            $fullName = $match ? trim($match['FirstName']) . ' ' . trim($match['LastName']) : $username;

            $sql = "UPDATE wst_Users SET Username = ?, RoleID = ?, PhaseID = ?, AreaID = ?, FullName = ? WHERE UserID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $roleId, $phaseId, $areaId, $fullName, $userId]);
            $msg = "Personnel assignment updated successfully";
        } else {
            // Insert
            // Check if already exists
            $check = $conn->prepare("SELECT COUNT(*) FROM wst_Users WHERE Username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("This user is already assigned a role in the system");
            }

            // Also explicitly grab the FullName
            $nameSql = $conn->prepare("SELECT FirstName, LastName FROM LRNPH_E.dbo.lrn_master_list WHERE BiometricsID = ? AND IsActive = 1");
            $nameSql->execute([$username]);
            $match = $nameSql->fetch(PDO::FETCH_ASSOC);
            $fullName = $match ? trim($match['FirstName']) . ' ' . trim($match['LastName']) : $username;

            $sql = "INSERT INTO wst_Users (Username, RoleID, PhaseID, AreaID, FullName) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $roleId, $phaseId, $areaId, $fullName]);
            $msg = "Personnel assigned successfully";
        }
        echo json_encode(['success' => true, 'message' => $msg]);

    } elseif ($action === 'save_master') {
        $table  = $_POST['table']  ?? '';
        $column = $_POST['column'] ?? '';
        $value  = trim($_POST['value']  ?? '');
        $idVal  = $_POST['idVal']   ?? '';
        $idCol  = $_POST['idCol']   ?? '';

        // Whitelist security for tables and columns
        if (!isTableAllowed($table)) throw new Exception("Invalid table");
        if (!isIdentifierAllowed($column)) throw new Exception("Invalid column");

        if (empty($value)) throw new Exception("Value cannot be empty");

        // Format to Title Case
        $value = ucwords(strtolower($value));

        // Check for duplicate (excluding self if update)
        if ($idVal && $idCol) {
            if (!isIdentifierAllowed($idCol)) throw new Exception("Invalid ID column");
            $check = $conn->prepare("SELECT COUNT(*) FROM $table WHERE $column = ? AND $idCol != ?");
            $check->execute([$value, $idVal]);
        } else {
            $check = $conn->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
            $check->execute([$value]);
        }
        
        if ($check->fetchColumn() > 0) throw new Exception("This entry already exists");

        if ($idVal && $idCol) {
            $sql = "UPDATE $table SET $column = ?" . ($table === 'wst_LogTypes' ? ", PhaseID = ?" : "") . " WHERE $idCol = ?";
            $stmt = $conn->prepare($sql);
            $args = [$value];
            if ($table === 'wst_LogTypes') $args[] = $_POST['phaseId'] ?: null;
            $args[] = $idVal;
            $stmt->execute($args);
            $msg = "Entry updated successfully";
        } else {
            $sql = "INSERT INTO $table ($column" . ($table === 'wst_LogTypes' ? ", PhaseID" : "") . ") VALUES (?" . ($table === 'wst_LogTypes' ? ", ?" : "") . ")";
            $stmt = $conn->prepare($sql);
            $args = [$value];
            if ($table === 'wst_LogTypes') $args[] = $_POST['phaseId'] ?: null;
            $stmt->execute($args);
            $msg = "Entry added successfully";
        }

        echo json_encode(['success' => true, 'message' => $msg]);

    } elseif ($action === 'delete') {
        $table = $_POST['table'] ?? '';
        $idCol = $_POST['idCol'] ?? '';
        $idVal = $_POST['idVal'] ?? '';

        // Whitelist security for tables and columns
        if (!isTableAllowed($table)) throw new Exception("Invalid table");
        if (!isIdentifierAllowed($idCol)) throw new Exception("Invalid ID column");

        $sql = "DELETE FROM $table WHERE $idCol = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idVal]);

        echo json_encode(['success' => true, 'message' => "Record deleted successfully"]);
    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
