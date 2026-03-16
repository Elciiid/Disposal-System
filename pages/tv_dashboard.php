<?php
// Removed auth.php to prevent unexpected redirect loops on passive wall displays

require_once '../connection/database.php';
require_once '../utils/analytics_helper.php';

$phaseId = isset($_GET['phase']) ? (int)$_GET['phase'] : 2; // Default to Phase 2
$matrix = getTVBoardMetrics($conn, $phaseId);

// Date formatting for the header
$currentDate = strtoupper(date('F j, Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Display - Phase <?= $phaseId ?></title>
    <link rel="stylesheet" href="../styles/tv_display.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    <!-- Removed external Ionicons scripts to prevent Failed to Fetch CDN errors on locked networks -->
    <!-- Auto refresh every 5 minutes -->
    <meta http-equiv="refresh" content="300">
</head>
<body>
    <div class="tv-container">
        <header>
            <h1>PHASE <?= $phaseId ?></h1>
            <div class="as-of">as of <?= $currentDate ?></div>
        </header>

        <?php $matrixCount = count($matrix); ?>
        <div class="dashboard-grid items-<?= $matrixCount ?>">
            <?php foreach ($matrix as $catName => $data): 
                $cleanName = strtolower(str_replace(' ', '_', $catName));
                $imgPath = "../assets/img/products/{$cleanName}.png";
                
                // Analytics helper now natively returns the array of all LogTypes
                // submitted today formatted as [['label' => '...', 'data' => ['val' => X, 'trend' => 'Y']]]
                $metrics = $data['metrics'] ?? [];
            ?>
            <div class="product-card">
                <div class="card-image-wrapper">
                    <?php 
                        $customStyle = "";
                        if (str_contains($cleanName, 'bread') || str_contains($cleanName, 'macaron') || str_contains($cleanName, 'lmf')) {
                            $customStyle = 'style="transform: scale(0.8);"';
                        }
                    ?>
                    <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($catName) ?>" class="product-img" <?= $customStyle ?> onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22 viewBox=%220 0 120 120%22 fill=%22none%22%3e%3crect width=%22120%22 height=%22120%22 rx=%2216%22 fill=%22transparent%22/%3e%3ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22sans-serif%22 font-size=%2240%22 fill=%22%2394a3b8%22%3e?%3c/text%3e%3c/svg%3e';">
                </div>
                <div class="card-content">
                    <h2 class="card-title"><?= htmlspecialchars($catName) ?></h2>
                    
                    <div class="metrics-grid">
                        <?php foreach ($metrics as $m): 
                            $val = $m['data']['val'];
                            $trend = $m['data']['trend'];
                            $iconSvg = '';
                            $class = 'trend-stable';
                            
                            if ($trend === 'up') {
                                // Sharp red arrow up right
                                $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>';
                                $class = 'trend-up';
                            } elseif ($trend === 'down') {
                                // Sharp green arrow down right
                                $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="7" x2="17" y2="17"></line><polyline points="17 7 17 17 7 17"></polyline></svg>';
                                $class = 'trend-down';
                            }
                            
                            $finalDisplay = ($val > 0) ? number_format($val, ($val == (int)$val ? 0 : 2)) . ' KG' : '-';
                            
                            // If value is 0, only show "0 KG" if there is an active trend from yesterday
                            if ($val === 0.0) {
                                if ($trend !== 'stable') {
                                    $finalDisplay = '0 KG';
                                } else {
                                    $finalDisplay = '-';
                                }
                            }
                        ?>
                        <div class="metric-box">
                            <div class="metric-label"><?= htmlspecialchars($m['label']) ?></div>
                            <div class="metric-value-row">
                                <span class="metric-value-text"><?= $finalDisplay ?></span>
                                <?php if ($finalDisplay !== '-' && $iconSvg !== ''): ?>
                                <span class="trend-icon <?= $class ?>"><?= $iconSvg ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            console.log('TV Dashboard Initialized - Responsive Mode Active');
        });
    </script>
</body>
</html>
