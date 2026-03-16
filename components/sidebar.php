<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
// Check permissions for sidebar visibility
require_once __DIR__ . '/../auth/auth_helpers.php';
$hasSettingsAccess = hasPermission($conn, 'access_settings');
$hasHistoryAccess  = hasPermission($conn, 'view_history');
$canSubmitLogs    = hasPermission($conn, 'submit_logs');
$canViewOwn       = hasPermission($conn, 'view_own_submissions');
$canViewDailyProducts = hasPermission($conn, 'view_daily_products');

require_once __DIR__ . '/../api/approval_workflow.php';

// Check if user is part of the approval chain (any step)
[$stepNumber, $stepConfig] = getStepForUser($conn);
$hasApprovalAccess = ($stepNumber !== null);

// Count pending items for badge
$pendingCount = 0;
if ($hasApprovalAccess) {
    try {
        $pendingCount = count(getPendingRequests($conn, $_SESSION['wst_phase_id'] ?? null));
    } catch (PDOException $e) {
        $pendingCount = 0;
    }
}
?>
<!-- Mobile Sidebar Toggle -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
    <ion-icon name="menu-outline"></ion-icon>
</button>

<aside class="sidebar" id="mainSidebar">
    <div class="brand">
        <span class="brand-icon">✦</span> Disposal
    </div>
    
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <ion-icon name="grid-outline"></ion-icon> Dashboard
        </a>
        
        <?php if ($canSubmitLogs): ?>
        <a href="index.php" class="nav-link <?= $currentPage == 'index.php' ? 'active' : '' ?>">
            <ion-icon name="clipboard-outline"></ion-icon> Forms
        </a>
        <?php endif; ?>

        <?php if ($canViewOwn): ?>
        <a href="submissions.php" class="nav-link <?= $currentPage == 'submissions.php' ? 'active' : '' ?>">
            <ion-icon name="document-text-outline"></ion-icon> My Submissions
        </a>
        <?php endif; ?>

        <?php if ($hasHistoryAccess): ?>
        <a href="history.php" class="nav-link <?= $currentPage == 'history.php' ? 'active' : '' ?>">
            <ion-icon name="time-outline"></ion-icon> History Logs
        </a>
        <?php endif; ?>

        <?php if ($canViewDailyProducts): ?>
        <a href="daily_products.php" class="nav-link <?= $currentPage == 'daily_products.php' ? 'active' : '' ?>">
            <ion-icon name="today-outline"></ion-icon> Daily Products
        </a>
        <?php endif; ?>

        <?php if ($hasApprovalAccess): ?>
        <a href="supervisor.php" class="nav-link <?= $currentPage == 'supervisor.php' ? 'active' : '' ?>">
            <ion-icon name="checkmark-done-circle-outline"></ion-icon> Approvals
            <?php if($pendingCount > 0): ?>
                <span class="nav-badge" id="pendingBadge" style="background: var(--accent-yellow); color: #000;"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </nav>

    <!-- IT Logo at bottom of sidebar -->
    <img src="../assets/img/IT%20Footer.png" alt="IT Logo" class="sidebar-logo" style="margin-top: auto;">
</aside>

<!-- Sidebar Overlay (mobile/tablet) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
(function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar && overlay) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            var isOpen = sidebar.classList.contains('open');
            overlay.style.display = isOpen ? 'block' : 'none';
            toggle.style.display = isOpen ? 'none' : 'flex';
        });
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
            toggle.style.display = 'flex';
        });
        // Close on nav link click (mobile UX)
        sidebar.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1200) {
                    sidebar.classList.remove('open');
                    overlay.style.display = 'none';
                    toggle.style.display = 'flex';
                }
            });
        });
    }
})();
</script>
