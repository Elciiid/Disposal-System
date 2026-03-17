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
 * Fetch all records from a given master table.
 * Uses explicit double-quoted AS aliases to force PostgreSQL to return
 * mixed-case column keys (e.g. "PhaseID" not "phaseid").
 */
function fetchAllFromTable($conn, $tableName, $orderBy = '')
{
    if (!in_array($tableName, getAllowedTables())) {
        return [];
    }

    // Explicit column maps per table — PostgreSQL lowercases unquoted identifiers,
    // so SELECT * gives 'phaseid' not 'PhaseID'. The double-quoted AS aliases fix this.
    $columnMaps = [
        'wst_Phases'       => '"PhaseID", "PhaseName"',
        'wst_Areas'        => '"AreaID", "AreaName"',
        'wst_Shifts'       => '"ShiftID", "ShiftName"',
        'wst_LogTypes'     => '"TypeID", "TypeName"',
        'wst_PCategories'  => '"CategoryID", "CategoryName"',
        'wst_PDescriptions'=> '"DescriptionID", "CategoryID", "DescriptionName"',
        'wst_Roles'        => '"RoleID", "RoleName"',
        'wst_Users'        => '"UserID", "Username", "FullName", "EmployeeID", "RoleID", "PhaseID", "AreaID"',
    ];

    if (!isset($columnMaps[$tableName])) {
        // Fallback for any unmapped table: fetch and normalize keys to Title-Case won't work,
        // so just try SELECT * and hope for the best.
        $selectCols = '*';
    } else {
        // Build aliased SELECT: PhaseID AS "PhaseID", PhaseName AS "PhaseName"
        $cols = array_map(function($col) {
            $bare = trim($col, '"');
            return "$bare AS $col";
        }, explode(', ', $columnMaps[$tableName]));
        $selectCols = implode(', ', $cols);
    }

    $sql = "SELECT $selectCols FROM $tableName";
    if ($orderBy !== '') {
        $sql .= " ORDER BY $orderBy";
    }

    try {
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("fetchAllFromTable Error ($tableName): " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch wst_Logs joined with all master tables.
 * PostgreSQL / Neon compatible — no SQL Server syntax.
 *
 * @param PDO    $conn         Database connection
 * @param string $statusFilter 'Pending', 'Resolved', 'Approved', 'Declined', or 'All'
 * @param array  $filters      Optional filters: startDate, endDate, limit, phaseId, shiftId, areaId, typeId, categoryId
 * @param string $submittedBy  Optional: filter to a specific submitter username
 * @return array
 */
function getWasteLogs($conn, $statusFilter = 'All', $filters = [], $submittedBy = null)
{
    $params = [];

    $sql = "SELECT
                w.LogID          AS \"LogID\",
                w.LogDate        AS \"LogDate\",
                w.TypeID         AS \"TypeID\",
                w.PhaseID        AS \"PhaseID\",
                w.AreaID         AS \"AreaID\",
                w.ShiftID        AS \"ShiftID\",
                w.CategoryID     AS \"CategoryID\",
                w.DescriptionID  AS \"DescriptionID\",
                w.KG             AS \"KG\",
                w.Reason         AS \"Reason\",
                w.OtherTypeRemark AS \"OtherTypeRemark\",
                w.SubmittedBy    AS \"SubmittedBy\",
                w.CurrentStep    AS \"CurrentStep\",
                w.ApprovalStatus AS \"ApprovalStatus\",
                w.Step1ApprovedBy AS \"Step1ApprovedBy\",
                w.Step1ApprovedAt AS \"Step1ApprovedAt\",
                w.Step2ApprovedBy AS \"Step2ApprovedBy\",
                w.Step2ApprovedAt AS \"Step2ApprovedAt\",
                w.RejectedBy     AS \"RejectedBy\",
                w.RejectionReason AS \"RejectionReason\",
                t.TypeName       AS \"TypeName\",
                p.PhaseName      AS \"PhaseName\",
                c.CategoryName   AS \"CategoryName\",
                a.AreaName       AS \"AreaName\",
                s.ShiftName      AS \"ShiftName\",
                d.DescriptionName AS \"DescriptionName\",
                COALESCE(sub_u.FullName, w.SubmittedBy) AS \"SubmitterName\",
                sub_u.EmployeeID                         AS \"SubmitterEmployeeID\",
                COALESCE(apr_u.FullName,
                    CASE WHEN w.ApprovalStatus = 'Rejected' THEN w.RejectedBy
                         ELSE COALESCE(w.Step2ApprovedBy, w.Step1ApprovedBy)
                    END
                )                                        AS \"ApproverName\",
                apr_u.EmployeeID                         AS \"ApproverEmployeeID\",
                apr_u.Username                           AS \"ApproverBiometricsID\"
            FROM wst_Logs w
            LEFT JOIN wst_LogTypes     t     ON w.TypeID        = t.TypeID
            LEFT JOIN wst_Phases       p     ON w.PhaseID       = p.PhaseID
            LEFT JOIN wst_PCategories  c     ON w.CategoryID    = c.CategoryID
            LEFT JOIN wst_Areas        a     ON w.AreaID        = a.AreaID
            LEFT JOIN wst_Shifts       s     ON w.ShiftID       = s.ShiftID
            LEFT JOIN wst_PDescriptions d    ON w.DescriptionID = d.DescriptionID
            LEFT JOIN wst_Users        sub_u ON sub_u.Username  = w.SubmittedBy
            LEFT JOIN wst_Users        apr_u ON apr_u.Username  = CASE
                                                    WHEN w.ApprovalStatus = 'Rejected' THEN w.RejectedBy
                                                    ELSE COALESCE(w.Step2ApprovedBy, w.Step1ApprovedBy)
                                                END
            WHERE 1=1";

    // Status filter
    if ($statusFilter === 'Pending') {
        $sql .= " AND (w.ApprovalStatus = 'Pending' OR w.ApprovalStatus IS NULL)";
    } elseif ($statusFilter === 'Resolved') {
        $sql .= " AND w.ApprovalStatus IN ('Approved', 'Rejected')";
    } elseif ($statusFilter === 'Approved') {
        $sql .= " AND w.ApprovalStatus = 'Approved'";
    } elseif ($statusFilter === 'Declined') {
        // Support old 'Declined' label — maps to 'Rejected' in DB
        $sql .= " AND w.ApprovalStatus IN ('Declined', 'Rejected')";
    }

    // Submitter filter
    if ($submittedBy !== null) {
        $sql .= " AND w.SubmittedBy = :submittedBy";
        $params[':submittedBy'] = $submittedBy;
    }

    // Date filters
    if (!empty($filters['startDate'])) {
        $sql .= " AND w.LogDate >= :startDate";
        $params[':startDate'] = $filters['startDate'];
    }
    if (!empty($filters['endDate'])) {
        $pe = $filters['endDate'];
        if (strlen($pe) == 10) $pe .= ' 23:59:59';
        $sql .= " AND w.LogDate <= :endDate";
        $params[':endDate'] = $pe;
    }

    // Dimension filters
    if (!empty($filters['typeId']))     { $sql .= " AND w.TypeID = :typeId";         $params[':typeId']     = $filters['typeId']; }
    if (!empty($filters['phaseId']))    { $sql .= " AND w.PhaseID = :phaseId";       $params[':phaseId']    = $filters['phaseId']; }
    if (!empty($filters['areaId']))     { $sql .= " AND w.AreaID = :areaId";         $params[':areaId']     = $filters['areaId']; }
    if (!empty($filters['shiftId']))    { $sql .= " AND w.ShiftID = :shiftId";       $params[':shiftId']    = $filters['shiftId']; }
    if (!empty($filters['categoryId'])) { $sql .= " AND w.CategoryID = :categoryId"; $params[':categoryId'] = $filters['categoryId']; }

    $sql .= " ORDER BY w.LogDate DESC, w.LogID DESC";

    // PostgreSQL uses LIMIT (not TOP)
    if (isset($filters['limit']) && is_numeric($filters['limit'])) {
        $sql .= " LIMIT " . intval($filters['limit']);
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getWasteLogs Error: " . $e->getMessage());
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
