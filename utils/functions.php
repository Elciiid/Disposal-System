<?php
// includes/functions.php
require_once __DIR__ . '/photo_helper.php';

/**
 * Centralized whitelist of allowed master tables for security
 */
function getAllowedTables()
{
    return [
        'wst_Users',
        'wst_LogTypes',
        'wst_Phases',
        'wst_PCategories',
        'wst_Areas',
        'wst_Shifts',
        'wst_PDescriptions',
        'wst_Roles'
    ];
}

/**
 * Centralized whitelist of allowed columns/identifiers for security
 */
function getAllowedIdentifiers()
{
    return [
        'UserID', 'Username', 'RoleID', 'RoleName', 'PhaseID', 'PhaseName', 'AreaID', 'FullName',
        'TypeID', 'TypeName',
        'CategoryID', 'CategoryName',
        'AreaName',
        'ShiftID', 'ShiftName',
        'DescriptionID', 'DescriptionName'
    ];
}

/**
 * Check if a column/identifier is in the allowed whitelist
 */
function isIdentifierAllowed($name)
{
    return in_array($name, getAllowedIdentifiers());
}

/**
 * Check if a table is in the allowed whitelist
 */
function isTableAllowed($tableName)
{
    return in_array($tableName, getAllowedTables());
}

/**
 * Fetch all records from a given table
 * 
 * @param PDO $conn Database connection
 * @param string $tableName Name of the table to query
 * @param string $orderBy Column to order by
 * @return array Array of associative arrays containing the records
 */
function fetchAllFromTable($conn, $tableName, $orderBy = '')
{
    $allowedTables = getAllowedTables();

    if (!in_array($tableName, $allowedTables)) {
        return [];
    }

    $sql = "SELECT * FROM " . $tableName;
    if ($orderBy !== '') {
        $sql .= " ORDER BY " . $orderBy;
    }

    try {
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    catch (PDOException $e) {
        // Log error in a real app
        return [];
    }
}

/**
 * Fetch wst_Logs joined with all master tables.
 * 
 * @param PDO $conn Database connection
 * @param string $statusFilter 'Pending', 'Resolved', or 'All'
 * @param array $filters Optional filters like startDate, endDate, limit
 * @return array Array of logs
 */
function getWasteLogs($conn, $statusFilter = 'All', $filters = [], $submittedBy = null)
{

    // Check if limit is set, if so, we must inject it into the SELECT clause for SQL Server
    $topClause = "";
    if (isset($filters['limit']) && is_numeric($filters['limit'])) {
        $topClause = "TOP " . intval($filters['limit']) . " ";
    }

    $sql = "SELECT " . $topClause . "w.*, 
           t.TypeName, p.PhaseName, c.CategoryName, a.AreaName, s.ShiftName, d.DescriptionName,
           COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(u.FirstName, '') + ' ' + ISNULL(u.LastName, ''))), ''), NULLIF(LTRIM(RTRIM(lu.full_name COLLATE DATABASE_DEFAULT)), ''), w.SubmittedBy COLLATE DATABASE_DEFAULT) AS SubmitterName,
           COALESCE(NULLIF(LTRIM(RTRIM(u.EmployeeID COLLATE DATABASE_DEFAULT)), ''), w.SubmittedBy COLLATE DATABASE_DEFAULT) AS SubmitterEmployeeID,
           COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(au.FirstName, '') + ' ' + ISNULL(au.LastName, ''))), ''), NULLIF(LTRIM(RTRIM(alu.full_name COLLATE DATABASE_DEFAULT)), ''), (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT) AS ApproverName,
           COALESCE(NULLIF(LTRIM(RTRIM(au.EmployeeID COLLATE DATABASE_DEFAULT)), ''), (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT) AS ApproverEmployeeID,
           COALESCE(NULLIF(LTRIM(RTRIM(au.BiometricsID COLLATE DATABASE_DEFAULT)), ''), (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT) AS ApproverBiometricsID
    FROM wst_Logs w
    LEFT JOIN wst_LogTypes t ON w.TypeID = t.TypeID
    LEFT JOIN wst_Phases p ON w.PhaseID = p.PhaseID
    LEFT JOIN wst_PCategories c ON w.CategoryID = c.CategoryID
    LEFT JOIN wst_Areas a ON w.AreaID = a.AreaID
    LEFT JOIN wst_Shifts s ON w.ShiftID = s.ShiftID
    LEFT JOIN wst_PDescriptions d ON w.DescriptionID = d.DescriptionID
    LEFT JOIN LRNPH_E.dbo.lrn_master_list u ON w.SubmittedBy COLLATE DATABASE_DEFAULT = u.BiometricsID COLLATE DATABASE_DEFAULT
    LEFT JOIN LRNPH.dbo.lrnph_users lu ON w.SubmittedBy COLLATE DATABASE_DEFAULT = lu.username COLLATE DATABASE_DEFAULT
    LEFT JOIN LRNPH_E.dbo.lrn_master_list au ON (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT = au.BiometricsID COLLATE DATABASE_DEFAULT
    LEFT JOIN LRNPH.dbo.lrnph_users alu ON (CASE WHEN w.ApprovalStatus = 'Declined' THEN w.RejectedBy ELSE COALESCE(w.Step2ApprovedBy, w.Step1ApprovedBy) END) COLLATE DATABASE_DEFAULT = alu.username COLLATE DATABASE_DEFAULT
    WHERE 1=1";

    if ($statusFilter === 'Pending') {
        $sql .= " AND (w.ApprovalStatus = 'Pending' OR w.ApprovalStatus IS NULL)";
    }
    elseif ($statusFilter === 'Resolved') {
        $sql .= " AND w.ApprovalStatus IN ('Approved', 'Declined')";
    }
    elseif ($statusFilter === 'Approved') {
        $sql .= " AND w.ApprovalStatus = 'Approved'";
    }
    elseif ($statusFilter === 'Declined') {
        $sql .= " AND w.ApprovalStatus = 'Declined'";
    }

    $params = [];

    // Submitter Filtering
    if ($submittedBy !== null) {
        $sql .= " AND w.SubmittedBy = :submittedBy";
        $params[':submittedBy'] = $submittedBy;
    }

    // Date Filtering
    if (!empty($filters['startDate'])) {
        $sql .= " AND w.LogDate >= :startDate";
        $params[':startDate'] = $filters['startDate'];
    }
    if (!empty($filters['endDate'])) {
        $sql .= " AND w.LogDate <= :endDate";
        $pe = $filters['endDate'];
        if (strlen($pe) == 10) {
            $pe .= ' 23:59:59';
        }
        $params[':endDate'] = $pe;
    }

    // Additional Filters
    if (!empty($filters['typeId'])) {
        $sql .= " AND w.TypeID = :typeId";
        $params[':typeId'] = $filters['typeId'];
    }
    if (!empty($filters['phaseId'])) {
        $sql .= " AND w.PhaseID = :phaseId";
        $params[':phaseId'] = $filters['phaseId'];
    }
    if (!empty($filters['areaId'])) {
        $sql .= " AND w.AreaID = :areaId";
        $params[':areaId'] = $filters['areaId'];
    }
    if (!empty($filters['shiftId'])) {
        $sql .= " AND w.ShiftID = :shiftId";
        $params[':shiftId'] = $filters['shiftId'];
    }
    if (!empty($filters['categoryId'])) {
        $sql .= " AND w.CategoryID = :categoryId";
        $params[':categoryId'] = $filters['categoryId'];
    }

    $sql .= " ORDER BY w.LogDate DESC, w.LogID DESC";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    catch (PDOException $e) {
        handleSystemError("History Fetch Error: " . $e->getMessage());
        return [];
    }
}
/**
 * DRY: Shared error handler to prevent die() and show a nice UI message instead.
 */
function handleSystemError($message, $redirect = '../pages/dashboard.php')
{
    // Log error internally (could be expanded to file logging)
    error_log("System Error: " . $message);

    // Set user-friendly message
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $_SESSION['error_msg'] = "Something went wrong. Please try again or contact IT if the issue persists.";

    // Clean redirect
    header("Location: " . $redirect);
    exit();
}
?>
