<?php
/**
 * API endpoint for dashboard AJAX data.
 * Returns ALL dashboard metrics filtered by time scale.
 * 
 * Query params:
 *   scale = 'daily' | 'weekly' | 'monthly'
 */
header('Content-Type: application/json');

require_once '../connection/database.php';
require_once '../utils/analytics_helper.php';

$scale = $_GET['scale'] ?? 'daily';

// Validate scale
if (!in_array($scale, ['daily', 'weekly', 'monthly'])) {
    $scale = 'daily';
}

try {
    // Build date condition for SQL
    if ($scale === 'daily') {
        $dateCondition = "CAST(LogDate AS DATE) = CAST(GETDATE() AS DATE)";
    } elseif ($scale === 'weekly') {
        $dateCondition = "CAST(LogDate AS DATE) >= DATEADD(day, -DATEPART(dw, GETDATE()) + 1, CAST(GETDATE() AS DATE)) AND CAST(LogDate AS DATE) <= CAST(GETDATE() AS DATE)";
    } else {
        $dateCondition = "YEAR(LogDate) = YEAR(GETDATE()) AND MONTH(LogDate) = MONTH(GETDATE())";
    }

    // 1. Waste Processed stats
    $stats = getWasteStatsFiltered($conn, $scale);

    // 2. Pending approvals (filtered by time)
    $pendingCount = $conn->query(
        "SELECT COUNT(*) FROM wst_Logs WHERE ApprovalStatus = 'Pending' AND $dateCondition"
    )->fetchColumn() ?: 0;

    // 3. Approval Index (filtered by time)
    $totalInPeriod = $conn->query("SELECT COUNT(*) FROM wst_Logs WHERE $dateCondition")->fetchColumn() ?: 0;
    $approvedInPeriod = $conn->query("SELECT COUNT(*) FROM wst_Logs WHERE ApprovalStatus = 'Approved' AND $dateCondition")->fetchColumn() ?: 0;
    $efficiencyIndex = $totalInPeriod > 0 ? round(($approvedInPeriod / $totalInPeriod) * 100) : 0;

    // 4. Trend chart data
    if ($scale === 'daily') {
        $trends = getDailyWasteTrends($conn);
    } elseif ($scale === 'weekly') {
        $trends = getWeeklyWasteTrends($conn);
    } else {
        $trends = getMonthlyWasteTrends($conn);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'pending_count' => (int)$pendingCount,
            'efficiency_index' => (int)$efficiencyIndex,
            'trends' => $trends
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
