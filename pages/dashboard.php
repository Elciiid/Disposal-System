<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../utils/analytics_helper.php';
require_once '../utils/functions.php'; // Added this line as per instruction
require_once '../auth/access_control.php';

$currentUser = getCurrentUser();
// Dashboard is now public to all logged-in staff

// Note: pending data is now computed once here and shared with sidebar/topbar components
require_once '../api/approval_workflow.php';
$approvalCtx = getApprovalContext($conn);
$pendingLogs = $approvalCtx['pendingLogs'];
$latestPendingLogs = $approvalCtx['latestPendingLogs'];

try {
    // Daily date condition for initial load
    $dailyCond = "CAST(LogDate AS DATE) = CAST(GETDATE() AS DATE)";

    // Initial load: daily filtered stats
    $filteredStats = getWasteStatsFiltered($conn, 'daily');
    $totalLogs = $filteredStats['total_logs'];
    $totalKG = $filteredStats['total_kg'];
    $othersCount = $filteredStats['others_count'];
    $areaCount = $filteredStats['area_count'];

    // Pending approvals (daily)
    $pendingCount = $conn->query("SELECT COUNT(*) FROM wst_Logs WHERE ApprovalStatus = 'Pending' AND $dailyCond")->fetchColumn() ?: 0;

    // Approval Index (daily)
    $totalInPeriod = $conn->query("SELECT COUNT(*) FROM wst_Logs WHERE $dailyCond")->fetchColumn() ?: 0;
    $approvedInPeriod = $conn->query("SELECT COUNT(*) FROM wst_Logs WHERE ApprovalStatus = 'Approved' AND $dailyCond")->fetchColumn() ?: 0;
    $efficiencyIndex = $totalInPeriod > 0 ? round(($approvedInPeriod / $totalInPeriod) * 100) : 0;

    // Distribution by Category
    $distribution = getWasteDistributionByCategory($conn);
    
    // Initial load: daily trends
    $trends = getDailyWasteTrends($conn);
    
} catch(PDOException $e) {
    require_once '../utils/functions.php';
    handleSystemError("Dashboard Stats Error: " . $e->getMessage(), 'dashboard.php');
}
?>
<?php
$pageTitle = 'Dashboard - Waste Logs';
$extraCSS = ['supervisor.css'];
require_once '../components/header.php';
?>
<body>

<div class="dashboard-wrapper">
    <!-- Left Sidebar Panel -->
    <?php include '../components/sidebar.php'; ?>

    <!-- Main Content Panel -->
    <main class="main-content">
        <?php include '../components/topbar.php'; ?>

        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <h1 class="page-title" style="letter-spacing: -1px;">Dashboard Overview</h1>
                <p class="page-subtitle text-muted mt-1">Take control of your production waste today!</p>
            </div>
            <div class="d-flex align-items-center gap-3 pb-2">
                <span class="text-muted fw-medium d-none d-md-inline" style="font-size: 0.95rem;"><?= date('d F, Y') ?></span>
                <div class="dropdown">
                    <button class="btn bg-white rounded-pill shadow-sm fw-bold px-4 py-2 border-0 d-flex align-items-center gap-2 dropdown-toggle" id="global-scale-btn" type="button" data-bs-toggle="dropdown" style="font-size: 0.85rem;">
                        <ion-icon name="sync-outline"></ion-icon> Daily
                    </button>
                    <ul class="dropdown-menu shadow-lg border-0 rounded-4" style="margin-top: 4px !important;" id="global-scale-menu">
                        <li><a class="dropdown-item py-2 px-3 active" href="#" data-scale="daily">Daily</a></li>
                        <li><a class="dropdown-item py-2 px-3" href="#" data-scale="weekly">Weekly</a></li>
                        <li><a class="dropdown-item py-2 px-3" href="#" data-scale="monthly">Monthly</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="dashboard-grid" style="flex-grow: 1; min-height: 0;">
            <!-- Left Column: Total Logs -->
            <div class="data-card d-flex flex-column position-relative" style="overflow-y: hidden;" id="waste-processed-card">
                <div class="d-flex justify-content-between mb-2">
                    <div class="fw-bold d-flex align-items-center gap-2 fs-6">
                        <ion-icon name="flash-outline" class="fs-5"></ion-icon> Waste Processed
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2 mb-4 dash-animate-section">
                    <h2 class="stat-value" id="processed-total-logs"><?= number_format($totalLogs) ?></h2>
                    <span class="fw-bold" style="font-size: 1rem;">logs <span class="text-muted fw-normal ms-1" id="processed-scale-label">daily</span></span>
                </div>
                
                <!-- Circle Visuals -->
                <div class="dash-circles-wrap position-relative d-flex justify-content-center align-items-center my-4 dash-animate-section">
                    <div class="rounded-circle d-flex flex-column justify-content-center align-items-center shadow-sm dash-circle-pcs">
                        <span class="fw-bold text-dark dash-circle-val" id="processed-others"><?= $othersCount >= 1000 ? number_format($othersCount/1000, 1) . 'k' : number_format($othersCount) ?></span>
                        <span class="text-dark fw-medium dash-circle-label">others</span>
                    </div>
                    <div class="rounded-circle d-flex flex-column justify-content-center align-items-center shadow-sm text-white dash-circle-kg">
                        <span class="fw-bold dash-circle-val" id="processed-kg"><?= $totalKG >= 1000 ? number_format($totalKG/1000, 1) . 'k' : number_format($totalKG, 1) ?></span>
                        <span class="dash-circle-label" style="color: #a1a1aa;">kg</span>
                    </div>
                    <div class="rounded-circle d-flex flex-column justify-content-center align-items-center shadow-sm text-dark dash-circle-transf">
                        <span class="fw-bold dash-circle-val" id="processed-areas"><?= number_format($areaCount) ?></span>
                        <span class="fw-medium dash-circle-label">areas</span>
                    </div>
                </div>

                <div class="mt-4">
                    <?php 
                    $colors = ['#c4b5fd', '#202227', 'var(--accent-yellow)'];
                    foreach ($distribution as $index => $item): 
                        $percentage = $totalLogs > 0 ? round(($item['log_count'] / $totalLogs) * 100) : 0;
                        $color = $colors[$index % count($colors)];
                    ?>
                    <div class="d-flex justify-content-between align-items-end mb-1">
                        <span class="fw-bold" style="font-size: 1.5rem; line-height: 1;"><?= $percentage ?><span class="fs-6 text-muted fw-normal ms-1">%</span></span>
                        <span class="text-muted fw-bold d-flex align-items-center gap-2" style="font-size: 0.75rem; letter-spacing: 0.05em;"><?= strtoupper($item['CategoryName']) ?> <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:<?= $color ?>;"></span></span>
                    </div>
                    <div class="progress mb-4" style="height: 8px; border-radius: 10px; background-color: #f1f3f7;">
                        <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%; background-color: <?= $color ?>; border-radius: 10px;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Top Row -->
                <div class="dash-stat-row">
                    <!-- Pending Approvals -->
                    <div class="data-card d-flex flex-column bg-white" id="approvals-card">
                        <div class="d-flex justify-content-between mb-4">
                            <div class="fw-bold d-flex align-items-center gap-2 fs-6">
                                <ion-icon name="heart-outline" class="fs-5"></ion-icon> Approvals
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 dash-animate-section">
                            <div class="d-flex align-items-end gap-1">
                                <h2 class="stat-value" id="approvals-pending-count"><?= number_format($pendingCount) ?></h2>
                                <span class="fw-bold mb-1" style="font-size: 1rem;">pending</span>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-dark" style="font-size: 0.95rem;">Status</div>
                                <div class="text-muted" style="font-size: 0.85rem;">Active Tracking</div>
                            </div>
                        </div>
                        <!-- Bottom Activity Metric -->
                        <div class="flex-grow-1 d-flex flex-column justify-content-end mt-4">
                            <div class="rounded-4 p-3 border-0" style="background-color: #f8fafc;">
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="fw-bold d-flex align-items-center gap-2 fs-6">
                                        <ion-icon name="barbell-outline" class="fs-5"></ion-icon> Weight Sum
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 dash-animate-section">
                                    <div class="d-flex align-items-end gap-1">
                                        <h2 class="stat-value" style="font-size: 2.2rem;" id="approvals-weight-sum"><?= number_format($totalKG, 1) ?></h2>
                                        <span class="fw-bold mb-1" style="font-size: 0.95rem;">kg</span>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">Unit</div>
                                        <div class="text-muted" style="font-size: 0.85rem;">Metric Ton (Scaled)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recovery Index -->
                    <div class="data-card d-flex flex-column bg-white" id="approval-index-card">
                        <div class="d-flex justify-content-between mb-3">
                            <div class="fw-bold d-flex align-items-center gap-2 fs-6">
                                <ion-icon name="analytics-outline" class="fs-5"></ion-icon> Approval Index
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2 mb-4 dash-animate-section">
                            <h2 class="stat-value" id="approval-index-value"><?= $efficiencyIndex ?></h2><span class="fw-bold mb-1">%</span>
                            <span class="badge text-dark rounded-pill ms-2" style="background-color: var(--accent-green); padding: 0.4em 0.8em; margin-bottom: 2px;">Resolved</span>
                        </div>
                        
                        <!-- Scatter plot dots -->
                        <div class="mt-auto d-flex align-items-end justify-content-between gap-1 pb-2" style="height: 140px;">
                            <?php 
                            $colors = ['#c4b5fd', '#a78bfa', '#8b5cf6', '#ddd6fe'];
                            for($i=0; $i<16; $i++) {
                                echo '<div class="d-flex flex-column gap-1 justify-content-end h-100 w-100 align-items-center">';
                                $dots = rand(2, 6);
                                for($d=0; $d<$dots; $d++) {
                                    $color = $colors[array_rand($colors)];
                                    echo '<div style="width: 8px; height: 8px; background-color: '.$color.'; border-radius: 50%; opacity: 0.8;"></div>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row Dark Card -->
                <div class="data-card flex-grow-1 dark-card text-white d-flex flex-column" style="background-color: #202227;" id="waste-analysis-card">
                    <div class="d-flex justify-content-between mb-4">
                        <div class="fw-bold d-flex align-items-center gap-2 fs-6">
                            <ion-icon name="stats-chart-outline" class="fs-5 text-white"></ion-icon> Waste Analysis (<span id="trend-title-label">Daily</span>)
                        </div>
                    </div>

                    <div class="d-flex gap-5 mb-4 mt-2 dash-animate-section">
                        <div>
                            <div class="d-flex align-items-center gap-3 mb-1">
                                <div style="width: 4px; height: 35px; background-color: var(--accent-yellow); border-radius: 2px;"></div>
                                <h2 class="stat-value text-white m-0 d-flex align-items-baseline"><?= $efficiencyIndex ?><span class="fs-5 text-muted fw-normal ms-1">%</span></h2>
                            </div>
                            <div style="color: #a1a1aa; font-size: 0.85rem;" class="ms-4 ps-1 mt-1">Resolution Rate</div>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-3 mb-1">
                                <div style="width: 4px; height: 35px; background-color: #c4b5fd; border-radius: 2px;"></div>
                                <h2 class="stat-value text-white m-0 d-flex align-items-baseline" id="trend-total-kg"><?= $totalKG >= 1000 ? number_format($totalKG/1000, 1) . 'k' : number_format($totalKG, 0) ?><span class="fs-5 text-muted fw-normal ms-1">kg</span></h2>
                            </div>
                            <div style="color: #a1a1aa; font-size: 0.85rem;" class="ms-4 ps-1 mt-1">Total Weight</div>
                        </div>
                    </div>

                    <!-- Bar Chart -->
                    <div class="dash-chart-wrap" style="position: relative; flex-grow: 1; width: 100%;">
                        <div id="trend-bars-container" class="mt-auto d-flex justify-content-start align-items-end h-100 pb-3 px-4 pt-4 gap-4 hide-scrollbar" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow-x: auto; overflow-y: hidden;">
                            <?php 
                        $maxKg = 0;
                        foreach($trends as $t) { if($t['total_kg'] > $maxKg) $maxKg = $t['total_kg']; }
                        if($maxKg == 0) $maxKg = 1;

                        foreach($trends as $idx => $trend) {
                            $isLast = ($idx === count($trends) - 1);
                            $h1 = ($trend['total_kg'] / $maxKg) * 100;
                            $h2 = ($trend['others_count'] / (max($trend['others_count'], 1) * 1.5)) * 100;
                            
                            $color1 = $isLast ? 'var(--accent-yellow)' : 'rgba(255,255,255,0.05)';
                            $color2 = $isLast ? '#c4b5fd' : 'rgba(255,255,255,0.03)';
                            $border1 = $isLast ? 'none' : '1px solid rgba(255,255,255,0.1)';
                            
                            $label = $trend['label'] ?? ($trend['month_label'] ?? '');

                            echo '<div class="d-flex flex-column align-items-center gap-3" style="height: 100%; justify-content: flex-end; width: 60px; flex-shrink: 0;">';
                            echo '<div class="d-flex gap-2 align-items-end" style="height: 100%;">';
                            echo '<div title="'.number_format($trend['total_kg'],1).'kg" style="width: 16px; height: '.max($h1, 5).'%; background: '.($isLast ? $color1 : '#2a2c33').'; border-radius: 8px; border: '.$border1.'; position: relative; overflow: hidden;">';
                            echo '</div>';

                            echo '<div title="'.number_format($trend['others_count']).' others" style="width: 16px; height: '.max($h2, 3).'%; background: '.($isLast ? $color2 : '#2a2c33').'; border-radius: 8px; border: '.$border1.'; position: relative; overflow: hidden;">';
                            echo '</div>';
                            echo '</div>';
                            echo '<div class="fw-medium text-nowrap text-center mb-1 pb-1" style="color: '.($isLast ? 'white' : '#a1a1aa').'; font-size: 0.7rem; width: 100%; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;">'.$label.'</div>';
                            echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once '../components/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Dashboard AJAX transition animations */
.dash-animate-section {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.dash-animate-section.fade-out {
    opacity: 0;
    transform: translateY(8px);
}
.dash-animate-section.fade-in {
    opacity: 1;
    transform: translateY(0);
}
#trend-bars-container {
    transition: opacity 0.35s ease;
}
#trend-bars-container.fade-out {
    opacity: 0;
}
#trend-bars-container.fade-in {
    opacity: 1;
}
.bar-animate {
    animation: barGrow 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
@keyframes barGrow {
    from { height: 0% !important; }
}
.circle-pulse {
    animation: circlePop 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes circlePop {
    0%   { transform: scale(0.85); opacity: 0.5; }
    60%  { transform: scale(1.05); }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<script>
(function() {
    const API_BASE = '../api/get_dashboard_data.php';

    function fmtNum(n, decimals) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: decimals || 0, maximumFractionDigits: decimals || 0 });
    }
    function fmtCompact(n, decimals) {
        if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
        return fmtNum(n, decimals || 0);
    }

    function fadeSection(el, updateFn, delay) {
        delay = delay || 300;
        el.classList.remove('fade-in');
        el.classList.add('fade-out');
        setTimeout(() => {
            updateFn();
            el.classList.remove('fade-out');
            el.classList.add('fade-in');
        }, delay);
    }

    // Bar chart builder
    function buildBarsHTML(trends) {
        let maxKg = 0;
        trends.forEach(t => { if (t.total_kg > maxKg) maxKg = t.total_kg; });
        if (maxKg === 0) maxKg = 1;

        let html = '';
        trends.forEach((trend, idx) => {
            const isLast = (idx === trends.length - 1);
            const h1 = Math.max((trend.total_kg / maxKg) * 100, 5);
            const h2 = Math.max((trend.others_count / (Math.max(trend.others_count, 1) * 1.5)) * 100, 3);
            const color1 = isLast ? 'var(--accent-yellow)' : '#2a2c33';
            const color2 = isLast ? '#c4b5fd' : '#2a2c33';
            const border1 = isLast ? 'none' : '1px solid rgba(255,255,255,0.1)';
            const labelColor = isLast ? 'white' : '#a1a1aa';
            const label = trend.label || trend.month_label || '';

            html += `<div class="d-flex flex-column align-items-center gap-3" style="height: 100%; justify-content: flex-end; width: 60px; flex-shrink: 0;">`;
            html += `<div class="d-flex gap-2 align-items-end" style="height: 100%;">`;
            html += `<div title="${fmtNum(trend.total_kg, 1)}kg" class="bar-animate" style="width: 16px; height: ${h1}%; background: ${color1}; border-radius: 8px; border: ${border1};"></div>`;
            html += `<div title="${fmtNum(trend.others_count)} others" class="bar-animate" style="width: 16px; height: ${h2}%; background: ${color2}; border-radius: 8px; border: ${border1}; animation-delay: 0.08s;"></div>`;
            html += `</div>`;
            html += `<div class="fw-medium text-nowrap text-center mb-1 pb-1" style="color: ${labelColor}; font-size: 0.7rem; width: 100%; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;">${label}</div>`;
            html += `</div>`;
        });
        return html;
    }

    // =============================================
    //  GLOBAL TIME SCALE DROPDOWN
    // =============================================
    document.querySelectorAll('#global-scale-menu .dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const scale = this.dataset.scale;
            const label = this.textContent.trim();

            // Update global button & active state
            const btn = document.getElementById('global-scale-btn');
            btn.innerHTML = '<ion-icon name="sync-outline"></ion-icon> ' + label;
            document.querySelectorAll('#global-scale-menu .dropdown-item').forEach(i => {
                i.classList.toggle('active', i.dataset.scale === scale);
            });

            // Fetch ALL dashboard data for this scale
            fetch(API_BASE + '?scale=' + scale)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return;
                    const d = res.data;
                    const s = d.stats;

                    // --- 1. Waste Processed Card ---
                    const processedSections = document.querySelectorAll('#waste-processed-card .dash-animate-section');
                    processedSections.forEach(sec => {
                        fadeSection(sec, () => {
                            document.getElementById('processed-total-logs').textContent = fmtNum(s.total_logs);
                            document.getElementById('processed-scale-label').textContent = label.toLowerCase();
                            document.getElementById('processed-others').textContent = fmtCompact(s.others_count);
                            document.getElementById('processed-kg').textContent = fmtCompact(s.total_kg, 1);
                            document.getElementById('processed-areas').textContent = fmtNum(s.area_count);
                        });
                    });
                    // Pulse circles
                    document.querySelectorAll('#waste-processed-card .dash-circles-wrap > div').forEach(circle => {
                        circle.classList.remove('circle-pulse');
                        void circle.offsetWidth;
                        circle.classList.add('circle-pulse');
                    });

                    // --- 2. Approvals Card ---
                    const approvalsSections = document.querySelectorAll('#approvals-card .dash-animate-section');
                    approvalsSections.forEach(sec => {
                        fadeSection(sec, () => {
                            document.getElementById('approvals-pending-count').textContent = fmtNum(d.pending_count);
                            document.getElementById('approvals-weight-sum').textContent = fmtNum(s.total_kg, 1);
                        });
                    });

                    // --- 3. Approval Index Card ---
                    const indexSections = document.querySelectorAll('#approval-index-card .dash-animate-section');
                    indexSections.forEach(sec => {
                        fadeSection(sec, () => {
                            document.getElementById('approval-index-value').textContent = d.efficiency_index;
                        });
                    });

                    // --- 4. Waste Analysis Card ---
                    document.getElementById('trend-title-label').textContent = label;
                    const barsContainer = document.getElementById('trend-bars-container');
                    barsContainer.classList.remove('fade-in');
                    barsContainer.classList.add('fade-out');
                    setTimeout(() => {
                        barsContainer.innerHTML = buildBarsHTML(d.trends);
                        barsContainer.classList.remove('fade-out');
                        barsContainer.classList.add('fade-in');
                    }, 350);
                })
                .catch(err => console.error('Dashboard fetch error:', err));
        });
    });
})();
</script>
</body>
</html>
