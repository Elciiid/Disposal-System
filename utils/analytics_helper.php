<?php
/**
 * utils/analytics_helper.php
 * Helper functions for dashboard analytics.
 */

/**
 * Get top 3 distribution of waste by category.
 */
function getWasteDistributionByCategory($conn) {
    $sql = "SELECT c.CategoryName as category_name, COUNT(w.LogID) as log_count, SUM(w.KG) as total_kg
            FROM wst_PCategories c
            LEFT JOIN wst_Logs w ON c.CategoryID = w.CategoryID
            GROUP BY c.CategoryName
            ORDER BY log_count DESC
            LIMIT 3";
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
                TO_CHAR(w.LogDate, 'YYYY-MM') as month_key,
                SUM(w.KG) as total_kg,
                COUNT(CASE WHEN t.TypeName LIKE '%Other%' THEN 1 END) as others_count
            FROM wst_Logs w
            JOIN wst_LogTypes t ON w.TypeID = t.TypeID
            WHERE w.LogDate::date >= :year_start
            GROUP BY TO_CHAR(w.LogDate, 'YYYY-MM')";
            
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
    
    // Fetch aggregated data for the current week from PostgreSQL
    $sql = "SELECT 
                w.LogDate::date as date_val,
                SUM(w.KG) as total_kg,
                COUNT(CASE WHEN t.TypeName LIKE '%Other%' THEN 1 END) as others_count
            FROM wst_Logs w
            JOIN wst_LogTypes t ON w.TypeID = t.TypeID
            WHERE w.LogDate >= :start_of_week
            GROUP BY w.LogDate::date";
            
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
                w.LogDate::date as date_val,
                SUM(w.KG) as total_kg,
                COUNT(CASE WHEN t.TypeName LIKE '%Other%' THEN 1 END) as others_count
            FROM wst_Logs w
            JOIN wst_LogTypes t ON w.TypeID = t.TypeID
            WHERE w.LogDate::date >= :month_start
            GROUP BY w.LogDate::date";
            
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
 * All 4 metrics are fetched in a SINGLE SQL query to minimize Neon round-trips.
 */
function getWasteStatsFiltered($conn, $timeScale = 'daily') {
    try {
        // Build the date condition based on time scale
        if ($timeScale === 'daily') {
            $dateCondition = "w.LogDate::date = CURRENT_DATE";
        } elseif ($timeScale === 'weekly') {
            $dateCondition = "w.LogDate::date >= date_trunc('week', CURRENT_DATE)::date AND w.LogDate::date <= CURRENT_DATE";
        } else {
            $dateCondition = "EXTRACT(YEAR FROM w.LogDate) = EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM w.LogDate) = EXTRACT(MONTH FROM CURRENT_DATE)";
        }

        // Single query: all 4 metrics at once
        $sql = "SELECT
                    COUNT(w.LogID) as total_logs,
                    COALESCE(SUM(w.KG), 0) as total_kg,
                    COUNT(CASE WHEN t.TypeName ILIKE '%Other%' THEN 1 END) as others_count,
                    COUNT(DISTINCT w.AreaID) as area_count
                FROM wst_Logs w
                LEFT JOIN wst_LogTypes t ON w.TypeID = t.TypeID
                WHERE $dateCondition";

        $row = $conn->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'total_logs'   => (int)($row['total_logs']   ?? 0),
            'total_kg'     => (float)($row['total_kg']   ?? 0),
            'others_count' => (int)($row['others_count'] ?? 0),
            'area_count'   => (int)($row['area_count']   ?? 0)
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
 * Consolidated into 2 queries (all-time stats + today's log count) instead of 5.
 */
function getWasteStats($conn) {
    try {
        // Query 1: All-time aggregated stats in one shot
        $sql = "SELECT
                    COUNT(w.LogID) as total_logs,
                    COALESCE(SUM(w.KG), 0) as total_kg,
                    COUNT(CASE WHEN t.TypeName ILIKE '%Other%' THEN 1 END) as others_count,
                    COUNT(DISTINCT w.AreaID) as area_count
                FROM wst_Logs w
                LEFT JOIN wst_LogTypes t ON w.TypeID = t.TypeID";
        $row = $conn->query($sql)->fetch(PDO::FETCH_ASSOC);

        // Query 2: Today's count (requires separate WHERE)
        $todayLogsResult = $conn->query(
            "SELECT COUNT(*) FROM wst_Logs WHERE LogDate::date = CURRENT_DATE"
        )->fetchColumn();

        return [
            'total_logs'   => (int)($row['total_logs']   ?? 0),
            'today_logs'   => (int)($todayLogsResult     ?? 0),
            'total_kg'     => (float)($row['total_kg']   ?? 0),
            'others_count' => (int)($row['others_count'] ?? 0),
            'area_count'   => (int)($row['area_count']   ?? 0)
        ];
    } catch (PDOException $e) {
        return [
            'total_logs'   => 0,
            'today_logs'   => 0,
            'total_kg'     => 0,
            'others_count' => 0,
            'area_count'   => 0
        ];
    }
}

/**
 * Get metrics for the TV Dashboard based on Phase and Categories.
 * Uses a single batched SQL query to fetch today + yesterday weights
 * instead of N+1 nested loops — drastically reduces Neon round-trips.
 */
function getTVBoardMetrics($conn, $phaseId) {
    try {
        // Step 1: Define label→patterns map for this phase
        if ($phaseId == 1) {
            $targetTypes = [
                'Disposed Waste' => ['%Dispos%'],
                'Crumble'        => ['%Crumble%']
            ];
        } elseif ($phaseId == 2) {
            $targetTypes = [
                'Disposed Waste' => ['%Dispos%'],
                'Excess Dough'   => ['%Excess%']
            ];
        } elseif ($phaseId == 3) {
            $targetTypes = [
                'Shell'      => ['%Shell%'],
                'Base / Tart'=> ['%Base%', '%Tart%'],
                'Puff'       => ['%Puff%'],
                'Assembled'  => ['%Assembl%']
            ];
        } else {
            $targetTypes = [
                'Disposed Waste' => ['%Dispos%']
            ];
        }

        // Step 2: Build a single query that fetches today + yesterday,
        //         all categories, all type patterns in ONE round-trip
        $sql = "SELECT
                    c.CategoryName,
                    t.TypeName,
                    SUM(CASE WHEN w.LogDate::date = CURRENT_DATE          THEN w.KG ELSE 0 END) as today_kg,
                    SUM(CASE WHEN w.LogDate::date = CURRENT_DATE - INTERVAL '1 day' THEN w.KG ELSE 0 END) as yesterday_kg
                FROM wst_Logs w
                JOIN wst_PCategories c ON w.CategoryID = c.CategoryID
                JOIN wst_LogTypes    t ON w.TypeID     = t.TypeID
                WHERE w.PhaseID = :phaseId
                  AND w.LogDate::date >= CURRENT_DATE - INTERVAL '1 day'
                GROUP BY c.CategoryName, t.TypeName
                HAVING SUM(CASE WHEN w.LogDate::date = CURRENT_DATE THEN w.KG ELSE 0 END) > 0
                    OR SUM(CASE WHEN w.LogDate::date = CURRENT_DATE - INTERVAL '1 day' THEN w.KG ELSE 0 END) > 0";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':phaseId' => $phaseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [];
        }

        // Step 3: Index results by CategoryName + TypeName for fast lookup
        // Structure: $index[catName][typeName] = [today_kg, yesterday_kg]
        $index = [];
        foreach ($rows as $row) {
            $index[$row['CategoryName']][$row['TypeName']] = [
                'today_kg'     => (float)$row['today_kg'],
                'yesterday_kg' => (float)$row['yesterday_kg']
            ];
        }

        // Step 4: Build the $results array using the pattern map
        $results = [];
        foreach ($index as $catName => $typeMap) {
            $categoryData = ['metrics' => []];

            foreach ($targetTypes as $label => $patterns) {
                $todayVal     = 0;
                $yesterdayVal = 0;

                // Match DB rows where TypeName matches any pattern for this label
                foreach ($typeMap as $typeName => $vals) {
                    foreach ($patterns as $p) {
                        // Convert SQL LIKE pattern to a simple PHP str_contains check
                        $needle = trim($p, '%');
                        if (stripos($typeName, $needle) !== false) {
                            $todayVal     += $vals['today_kg'];
                            $yesterdayVal += $vals['yesterday_kg'];
                            break; // avoid double-counting if multiple patterns match
                        }
                    }
                }

                // Only include the metric if there was activity today
                if ($todayVal > 0 || $yesterdayVal > 0) {
                    $trend = 'stable';
                    if ($todayVal > $yesterdayVal) {
                        $trend = 'up';
                    } elseif ($todayVal < $yesterdayVal && $yesterdayVal > 0) {
                        $trend = 'down';
                    }

                    $categoryData['metrics'][] = [
                        'label' => strtoupper($label),
                        'data'  => ['val' => $todayVal, 'trend' => $trend]
                    ];
                }
            }

            if (!empty($categoryData['metrics'])) {
                $results[$catName] = $categoryData;
            }
        }

        return $results;
    } catch (PDOException $e) {
        error_log("getTVBoardMetrics Error: " . $e->getMessage());
        return [];
    }
}
?>
