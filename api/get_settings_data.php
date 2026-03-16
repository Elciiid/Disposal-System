<?php
require_once '../utils/functions.php';
require_once '../connection/database.php';
session_start();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get parameters
$table = $_GET['table'] ?? '';
$search = $_GET['search'] ?? '';

// Check if table is allowed
if (!isTableAllowed($table)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized table access']);
    exit;
}

try {
    global $conn;
    
    // Map table to primary key and name column (should match ui_helpers expectations)
    $tableMap = [
        'wst_Users' => ['id' => 'UserID', 'name' => 'Username'],
        'wst_PCategories' => ['id' => 'CategoryID', 'name' => 'CategoryName'],
        'wst_PDescriptions' => ['id' => 'DescriptionID', 'name' => 'DescriptionName'],
        'wst_LogTypes' => ['id' => 'TypeID', 'name' => 'TypeName'],
        'wst_Shifts' => ['id' => 'ShiftID', 'name' => 'ShiftName'],
        'wst_Phases' => ['id' => 'PhaseID', 'name' => 'PhaseName'],
        'wst_Areas' => ['id' => 'AreaID', 'name' => 'AreaName'],
        'wst_Roles' => ['id' => 'RoleID', 'name' => 'RoleName']
    ];

    if (!isset($tableMap[$table])) {
        echo json_encode(['success' => false, 'message' => 'Configuration not found for table: ' . $table]);
        exit;
    }

    $params = [];

    if ($table === 'wst_Users') {
        $sql = "SELECT u.*, r.RoleName, p.PhaseName, a.AreaName, 
                       LTRIM(RTRIM(ISNULL(m.FirstName, '') + ' ' + ISNULL(m.LastName, ''))) as FullName
                FROM wst_Users u
                LEFT JOIN wst_Roles r ON u.RoleID = r.RoleID
                LEFT JOIN wst_Phases p ON u.PhaseID = p.PhaseID
                LEFT JOIN wst_Areas a ON u.AreaID = a.AreaID
                LEFT JOIN LRNPH_E.dbo.lrn_master_list m ON u.Username COLLATE DATABASE_DEFAULT = m.BiometricsID COLLATE DATABASE_DEFAULT";
        if ($search) {
            $sql .= " WHERE u.Username LIKE ? OR m.FirstName LIKE ? OR m.LastName LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY u.FullName ASC";
    } elseif ($table === 'wst_LogTypes') {
        $sql = "SELECT t.*, p.PhaseName 
                FROM wst_LogTypes t
                LEFT JOIN wst_Phases p ON t.PhaseID = p.PhaseID";
        if ($search) {
            $sql .= " WHERE t.TypeName LIKE ?";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY t.TypeName ASC";
    } else {
        $idCol = $tableMap[$table]['id'];
        $nameCol = $tableMap[$table]['name'];

        $sql = "SELECT * FROM $table";
        if ($search) {
            $sql .= " WHERE $nameCol LIKE ?";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY $nameCol ASC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'data' => $data,
        'config' => $tableMap[$table]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
