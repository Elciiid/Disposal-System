<?php
// Removed auth.php to prevent unexpected redirect loops on passive wall displays

require_once '../connection/database.php';
require_once '../utils/analytics_helper.php';

$phaseId = isset($_GET['phase']) ? (int)$_GET['phase'] : 2; // Default to Phase 2
$matrix = getTVBoardMetrics($conn, $phaseId);

// Date formatting for the header
$currentDate = strtoupper(date('F j, Y'));

// Extract headers from first row
$metricHeaders = [];
$firstCatData = reset($matrix);
if ($firstCatData && isset($firstCatData['metrics'])) {
    foreach ($firstCatData['metrics'] as $m) {
        $metricHeaders[] = $m['label'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Display 2 (Clay) - Phase <?= $phaseId ?></title>
    <link rel="stylesheet" href="../styles/tv2_display.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700&family=Nunito:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <meta http-equiv="refresh" content="300">
</head>
<body>
    <div class="tv-container">
        <header class="main-header">
            <h1 class="phase-title">PHASE <?= $phaseId ?></h1>
            <div class="as-of">as of <?= $currentDate ?></div>
        </header>

        <div class="clay-table">
            <div class="clay-row clay-header-row">
                <div class="clay-cell item-cell-header"></div>
                <?php foreach ($metricHeaders as $header): ?>
                    <div class="clay-cell header-cell"><?= htmlspecialchars($header) ?></div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($matrix as $catName => $data): 
                $cleanName = strtolower(str_replace(' ', '_', $catName));
                $imgPath = "../assets/img/products/{$cleanName}.png";
                $metrics = $data['metrics'] ?? [];
            ?>
            <div class="clay-row data-row">
                <div class="clay-cell item-cell">
                    <div class="item-name"><?= htmlspecialchars(strtoupper($catName)) ?></div>
                    <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($catName) ?>" class="item-img" onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22 viewBox=%220 0 120 120%22 fill=%22none%22%3e%3crect width=%22120%22 height=%22120%22 rx=%2216%22 fill=%22transparent%22/%3e%3ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22sans-serif%22 font-size=%2240%22 fill=%22%2394a3b8%22%3e?%3c/text%3e%3c/svg%3e';">
                </div>
                
                <?php foreach ($metrics as $m): 
                    $val = $m['data']['val'];
                    $trend = $m['data']['trend'];
                    $iconSvg = '';
                    $class = 'trend-stable';
                    
                    if ($trend === 'up') {
                        // Sharp red arrow straight up
                        $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>';
                        $class = 'trend-up';
                    } elseif ($trend === 'down') {
                        // Sharp green arrow straight down
                        $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>';
                        $class = 'trend-down';
                    }
                    
                    $finalDisplay = ($val > 0) ? number_format($val, ($val == (int)$val ? 0 : 2)) . ' KG' : '-';
                    
                    if ($val === 0.0) {
                        if ($trend !== 'stable') {
                            $finalDisplay = '0 KG';
                        } else {
                            $finalDisplay = '-';
                        }
                    }
                ?>
                <div class="clay-cell data-cell">
                    <span class="metric-value-text"><?= $finalDisplay ?></span>
                    <?php if ($finalDisplay !== '-' && $iconSvg !== ''): ?>
                    <span class="trend-icon <?= $class ?>"><?= $iconSvg ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            console.log('TV2 Dashboard Initialized - Claymorphism Mode Active');
        });
    </script>
</body>
</html>
