<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../utils/functions.php';

// Check access (similar to history.php)
$userRole = $_SESSION['wst_role_name'] ?? '';
if (!hasSettingsAccess($conn, $_SESSION['username'] ?? '', $userRole)) {
    die("Unauthorized access");
}

// Collect filters from $_GET
$filters = [
    'startDate'  => $_GET['startDate'] ?? '',
    'endDate'    => $_GET['endDate'] ?? '',
    'limit'      => $_GET['limit'] ?? '999999', // Default to all for export
    'phaseId'    => $_GET['phaseId'] ?? '',
    'shiftId'    => $_GET['shiftId'] ?? '',
    'areaId'     => $_GET['areaId'] ?? '',
    'typeId'     => $_GET['typeId'] ?? '',
    'categoryId' => $_GET['categoryId'] ?? '',
    'status'     => $_GET['status'] ?? 'All'
];

// Fetch data using the same function as history.php
$logs = getWasteLogs($conn, $filters['status'], $filters);

// Clear any previous output buffers to avoid corrupting the CSV
if (ob_get_length()) ob_clean();

// Set headers for CSV download
$filename = "disposal-logs_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel compatibility (UTF-8)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV Header
fputcsv($output, [
    'Date & Time',
    'Status',
    'Phase',
    'Shift',
    'Area',
    'Type',
    'Category',
    'PCS',
    'KG',
    'Submitted By',
    'Approved By (Biometrics ID)',
    'Approver Name',
    'Submission Reason',
    'Rejection Reason'
]);

// Add Data Rows
foreach ($logs as $log) {
    // Format date for better Excel compatibility (Short Date + Time)
    $logDate = !empty($log['LogDate']) ? date('m/d/Y h:i A', strtotime($log['LogDate'])) : '';
    
    fputcsv($output, [
        $logDate,
        $log['ApprovalStatus'] ?? 'Pending',
        $log['PhaseName'],
        $log['ShiftName'],
        $log['AreaName'],
        $log['TypeName'],
        $log['CategoryName'],
        number_format((float)($log['PCS'] ?? 0), 0, '.', ''),
        number_format((float)($log['KG'] ?? 0), 2, '.', ''),
        $log['SubmitterName'],
        $log['ApproverBiometricsID'],
        $log['ApproverName'],
        $log['Reason'],
        $log['RejectionReason'] ?? ''
    ]);
}

fclose($output);
exit();

