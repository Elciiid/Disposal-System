<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/access_control.php';

$currentUser = getCurrentUser();
if (!hasSettingsAccess($conn, $currentUser['username'], $_SESSION['wst_role_name'] ?? null)) {
    echo json_encode([]);
    exit();
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    // Search directly in LRNPH_E master list for all active employees
    $sql = "
        SELECT TOP 10 
            m.BiometricsID as username, 
            LTRIM(RTRIM(ISNULL(m.FirstName, '') + ' ' + ISNULL(m.LastName, ''))) as full_name,
            m.PositionTitle
        FROM LRNPH_E.dbo.lrn_master_list m
        WHERE (m.BiometricsID LIKE ? OR m.FirstName LIKE ? OR m.LastName LIKE ?)
        AND m.IsActive = 1
        ORDER BY m.BiometricsID ASC
    ";
    
    $searchTerm = "%$query%";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode([]);
}
