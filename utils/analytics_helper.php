<?php
/**
 * utils/analytics_helper.php
 * Helper functions for dashboard analytics.
 */

/**
 * Get top 3 distribution of waste by category.
 */
function getWasteDistributionByCategory($conn) {
    $sql = "SELECT TOP 3 c.CategoryName, COUNT(w.LogID) as log_count, SUM(w.KG) as total_kg, SUM(w.PCS) as total_pcs
            FROM wst_PCategories c
            LEFT JOIN wst_Logs w ON c.CategoryID = w.CategoryID
            GROUP BY c.CategoryName
            ORDER BY log_count DESC";
    try {
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get monthly waste trends (last 7 months).
 */
function getMonthlyWasteTrends($conn) {
    // Generate a timeline for months occurring ONLY in the current year up to the current month
    $dataMap = [];
    $today = new DateTime();
    $currentYear = $today->format('Y');
    $currentMonth = (int)$today->format('n'); // 1-12
    
    // Build array forward from January to the current month
    for ($m = 1; $m <= $currentMonth; $m++) {
        $dateObj = DateTime::createFromFormat('!Y-n', "$currentYear-$m");
        $key = $dateObj->format('Y-m'); // YYYY-MM
        
        $dataMap[$key] = [
            'month_label' => $dateObj->format('M'),
            'year_num' => $currentYear,
            'month_num' => $m,
            'total_kg' => 0,
            'others_count' => 0
        ];
    }
    
    // Fetch aggregated data for the current year
    $yearStartStr = "$currentYear-01-01";
    $sql = "SELECT 
                FORMAT(w.LogDate, 'yyyy-MM') as month_key,
                SUM(w.KG) as total_kg,
                COUNT(CASE WHEN t.TypeName LIKE '%Other%' THEN 1 END) as others_count
            FROM wst_Logs w
            JOIN wst_LogTypes t ON w.TypeID = t.TypeID
            WHERE CAST(w.LogDate AS DATE) >= :year_start
            GROUP BY FORMAT(w.LogDate, 'yyyy-MM')";
            
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':year_start' => $yearStartStr]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $monthKey = $row['month_key']; // Expected format YYYY-MM
            if ($monthKey && isset($dataMap[$monthKey])) {
                $dataMap[$monthKey]['total_kg'] += $row['total_kg'];
                $dataMap[$monthKey]['others_count'] += $row['others_count'];
            }
        }
    } catch (PDOException $e) {
        // Soft fail gracefully and return the 0-filled timeline
    }
    
    return array_values($dataMap);
}

/**
 * Get daily waste trends (last 7 days).
 */
function getDailyWasteTrends($conn) {
    // Generate a timeline from the start of the current week (Sunday) up to today
    $dataMap = [];
    $today = new DateTime();
    $startOfWeek = clone $today;
    
    // PHP 'w' format: 0 (for Sunday) through 6 (for Saturday)
    $dayOfWeek = (int)$today->format('w');
    if ($dayOfWeek > 0) {
        $startOfWeek->modify("-$dayOfWeek days");
    }
    
    // Build array forward from start of week up to today
    $currentDate = clone $startOfWeek;
    $endDate = clone $today;
    $endDate->modify('+1 day'); // To include today in the DatePeriod or while loop
    
    while ($currentDate < $endDate) {
        $key = $currentDate->format('Y-m-d');
        $dataMap[$key] = [
            'label' => $currentDate->format('d M'),
            'date_val' => $key,
            'total_kg' => 0,
            'others_count' => 0
        ];
        $currentDate->modify('+1 day');
    }
    
    // Fetch aggregated data for the current week from SQL Server
    $sql = "SELECT 
                CAST(w.LogDate AS DATE) as date_val,
                SUM(w.KG) as total_kg,
                COUNT(CASE WHEN t.TypeName LIKE '%Other%' THEN 1 END) as others_count
            FROM wst_Logs w
            JOIN wst_LogTypes t ON w.TypeID = t.TypeID
            WHERE w.LogDate >= :start_of_week
            GROUP BY CAST(w.LogDate AS DATE)";
            
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':start_of_week' => $startOfWeek->format('Y-m-d')]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $dateStr = $row['date_val'];
            if ($dateStr) {
                // Ensure date string maps correctly to YYYY-MM-DD
                $formattedDate = substr($dateStr, 0, 10);
                if (isset($dataMap[$formattedDate])) {
                    $dataMap[$formattedDate]['total_kg'] += $row['total_kg'];
                    $dataMap[$formattedDate]['others_count'] += $row['others_count'];
                }
            }
        }
    } catch (PDOException $e) {
        // Soft fail gracefully and return the 0-filled timeline
    }
    
    return array_values($dataMap);
}

/**
 * Get weekly waste trends (last 7 weeks).
 */
function getWeeklyWasteTrends($conn) {
    // Generate a timeline for 7-day blocks occurring in the current month up to today
    $dataMap = [];
    $today = new DateTime();
    $currentDay = (int)$today->format('j');
    $currentWeekOfMonth = ceil($currentDay / 7);
    $monthStr = $today->format('M');
    $yearMonth = $today->format('Y-m');
    
    // Build array forward so Week 1 comes before Week 2
    for ($w = 1; $w <= $currentWeekOfMonth; $w++) {
        $key = "$yearMonth-$w";
        $dataMap[$key] = [
            'label' => "Week $w $monthStr",
            'key' => $key,
            'total_kg' => 0,
            'others_count' => 0
        ];
    }
    
    // Fetch aggregated daily data for the current month
    $monthStartStr = $today->format('Y-m-01');
    $sql = "SELECT 
                CAST(w.LogDate AS DATE) as date_val,
                SUM(w.KG) as total_kg,
                COUNT(CASE WHEN t.TypeName LIKE '%Other%' THEN 1 END) as others_count
            FROM wst_Logs w
            JOIN wst_LogTypes t ON w.TypeID = t.TypeID
            WHERE CAST(w.LogDate AS DATE) >= :month_start
            GROUP BY CAST(w.LogDate AS DATE)";
            
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':month_start' => $monthStartStr]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $dateStr = $row['date_val'];
            if ($dateStr) {
                // Determine 7-day block mapping for the log date
                $formattedDate = substr($dateStr, 0, 10);
                $logDate = new DateTime($formattedDate);
                
                $day = (int)$logDate->format('j');
                $w = ceil($day / 7);
                $key = $logDate->format('Y-m') . '-' . $w;
                
                if (isset($dataMap[$key])) {
                    $dataMap[$key]['total_kg'] += $row['total_kg'];
                    $dataMap[$key]['others_count'] += $row['others_count'];
                }
            }
        }
    } catch (PDOException $e) {
        // Soft fail gracefully and return the 0-filled timeline
    }
    
    return array_values($dataMap);
}

/**
 * Get filtered counts and weights based on time scale (daily, weekly, monthly).
 */
function getWasteStatsFiltered($conn, $timeScale = 'daily') {
    try {
        // Build the date condition based on time scale
        if ($timeScale === 'daily') {
            $dateCondition = "CAST(LogDate AS DATE) = CAST(GETDATE() AS DATE)";
        } elseif ($timeScale === 'weekly') {
            // Current week (Sunday to Saturday)
            $dateCondition = "CAST(LogDate AS DATE) >= DATEADD(day, -DATEPART(dw, GETDATE()) + 1, CAST(GETDATE() AS DATE)) AND CAST(LogDate AS DATE) <= CAST(GETDATE() AS DATE)";
        } else {
            // Current month
            $dateCondition = "YEAR(LogDate) = YEAR(GETDATE()) AND MONTH(LogDate) = MONTH(GETDATE())";
        }

        // Total filtered logs
        $totalLogs = $conn->query("SELECT COUNT(*) FROM wst_Logs WHERE $dateCondition")->fetchColumn();

        // Filtered weights
        $stmt = $conn->query("SELECT SUM(KG) as total_kg, SUM(PCS) as total_pcs FROM wst_Logs WHERE $dateCondition");
        $weights = $stmt->fetch(PDO::FETCH_ASSOC);

        // Filtered "Others" count
        $stmt = $conn->query("SELECT COUNT(w.LogID) 
                              FROM wst_Logs w 
                              JOIN wst_LogTypes t ON w.TypeID = t.TypeID 
                              WHERE t.TypeName LIKE '%Other%' AND $dateCondition");
        $othersCount = $stmt->fetchColumn();

        // Filtered distinct area count
        $stmt = $conn->query("SELECT COUNT(DISTINCT AreaID) FROM wst_Logs WHERE $dateCondition");
        $areaCount = $stmt->fetchColumn();

        return [
            'total_logs'   => $totalLogs ?: 0,
            'total_kg'     => $weights['total_kg'] ?: 0,
            'others_count' => $othersCount ?: 0,
            'area_count'   => $areaCount ?: 0
        ];
    } catch (PDOException $e) {
        return [
            'total_logs'   => 0,
            'total_kg'     => 0,
            'others_count' => 0,
            'area_count'   => 0
        ];
    }
}

/**
 * Get general counts and weights.
 */
function getWasteStats($conn) {
    try {
        // Total logs
        $totalLogsResult = $conn->query("SELECT COUNT(*) FROM wst_Logs")->fetchColumn();
        
        // Logs today
        $todayLogsResult = $conn->query("SELECT COUNT(*) FROM wst_Logs WHERE LogDate = CAST(GETDATE() AS DATE)")->fetchColumn();
        
        // Weights
        $stmt = $conn->query("SELECT SUM(KG) as total_kg, SUM(PCS) as total_pcs FROM wst_Logs");
        $weights = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log types distribution (Waste vs Transfer)
        $stmt = $conn->query("SELECT t.TypeName, COUNT(w.LogID) as count 
                              FROM wst_LogTypes t 
                              LEFT JOIN wst_Logs w ON t.TypeID = w.TypeID 
                              GROUP BY t.TypeName");
        $types = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Shorthand counts
        $othersCount = 0;
        foreach ($types as $name => $count) {
            if (stripos($name, 'Other') !== false) {
                $othersCount += $count;
            }
        }

        // Distinct area count
        $areaCount = $conn->query("SELECT COUNT(DISTINCT AreaID) FROM wst_Logs")->fetchColumn() ?: 0;

        return [
            'total_logs'   => $totalLogsResult ?: 0,
            'today_logs'   => $todayLogsResult ?: 0,
            'total_kg'     => $weights['total_kg'] ?: 0,
            'others_count' => $othersCount,
            'area_count'   => $areaCount
        ];
    } catch (PDOException $e) {
        return [
            'total_logs' => 0,
            'today_logs' => 0,
            'total_kg' => 0,
            'total_pcs' => 0,
            'types' => []
        ];
    }
}

/**
 * Get metrics for the TV Dashboard based on Phase and Categories.
 * Provides weight and trend (up, down, stable) for "Disposal" and "Excess Dough".
 */
function getTVBoardMetrics($conn, $phaseId) {
    try {
        $results = [];

        // 1. Find all categories that have logs TODAY for this phase
        $sqlTodayCats = "SELECT DISTINCT w.CategoryID, c.CategoryName 
                         FROM wst_Logs w
                         JOIN wst_PCategories c ON w.CategoryID = c.CategoryID
                         WHERE w.PhaseID = :phaseId
                         AND CAST(w.LogDate AS DATE) = CAST(GETDATE() AS DATE)";
        $stmtCats = $conn->prepare($sqlTodayCats);
        $stmtCats->execute([':phaseId' => $phaseId]);
        $activeCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

        if (empty($activeCats)) {
            return []; // Nothing logged today in this phase
        }

        foreach ($activeCats as $cat) {
            $catId = $cat['CategoryID'];
            $catName = $cat['CategoryName'];
            $categoryData = ['metrics' => []];

            if ($phaseId == 1) {
                $targetTypes = [
                    'Disposed Waste' => ['%Dispos%'],
                    'Crumble' => ['%Crumble%']
                ];
            } elseif ($phaseId == 2) {
                $targetTypes = [
                    'Disposed Waste' => ['%Dispos%'],
                    'Excess Dough' => ['%Excess%']
                ];
            } elseif ($phaseId == 3) {
                $targetTypes = [
                    'Shell' => ['%Shell%'],
                    'Base / Tart' => ['%Base%', '%Tart%'],
                    'Puff' => ['%Puff%'],
                    'Assembled' => ['%Assembl%']
                ];
            } else {
                $targetTypes = [
                    'Disposed Waste' => ['%Dispos%']
                ];
            }

            foreach ($targetTypes as $label => $patterns) {
                $todayVal = 0;
                $yesterdayVal = 0;

                foreach ($patterns as $typePattern) {
                    $sqlToday = "SELECT SUM(w.KG) as total 
                                 FROM wst_Logs w
                                 JOIN wst_LogTypes t ON w.TypeID = t.TypeID
                                 WHERE w.CategoryID = :catId 
                                 AND w.PhaseID = :phaseId
                                 AND t.TypeName LIKE :typePattern
                                 AND CAST(w.LogDate AS DATE) = CAST(GETDATE() AS DATE)";
                    
                    $stmt = $conn->prepare($sqlToday);
                    $stmt->execute([
                        ':catId' => $catId,
                        ':phaseId' => $phaseId,
                        ':typePattern' => $typePattern
                    ]);
                    $todayVal += (float)$stmt->fetchColumn() ?: 0;
                    
                    $sqlYesterday = "SELECT SUM(w.KG) as total 
                                     FROM wst_Logs w
                                     JOIN wst_LogTypes t ON w.TypeID = t.TypeID
                                     WHERE w.CategoryID = :catId 
                                     AND w.PhaseID = :phaseId
                                     AND t.TypeName LIKE :typePattern
                                     AND CAST(w.LogDate AS DATE) = CAST(DATEADD(day, -1, GETDATE()) AS DATE)";
                    
                    $stmt = $conn->prepare($sqlYesterday);
                    $stmt->execute([
                        ':catId' => $catId,
                        ':phaseId' => $phaseId,
                        ':typePattern' => $typePattern
                    ]);
                    $yesterdayVal += (float)$stmt->fetchColumn() ?: 0;
                }
                
                $trend = 'stable';
                if ($todayVal > $yesterdayVal) {
                    $trend = 'up';
                } elseif ($todayVal < $yesterdayVal && $yesterdayVal > 0) {
                    $trend = 'down';
                }
                
                $categoryData['metrics'][] = [
                    'label' => strtoupper($label),
                    'data' => [
                        'val' => $todayVal,
                        'trend' => $trend
                    ]
                ];
            }
            
            $results[$catName] = $categoryData;
        }

        return $results;
    } catch (PDOException $e) {
        error_log("getTVBoardMetrics Error: " . $e->getMessage());
        return [];
    }
}
?>
