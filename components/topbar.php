<?php
// Requires $currentUser and $conn to be defined by the parent page.
// Uses $pendingLogs/$pendingCount/$latestPendingLogs if pre-set by parent;
// otherwise computes them here (fallback for pages that don't pre-compute).
require_once __DIR__ . '/../utils/functions.php';
require_once __DIR__ . '/../api/approval_workflow.php';

if (!isset($pendingLogs)) {
    $userRoleName = $_SESSION['wst_role_name'] ?? null;
    $userPhaseId  = $_SESSION['wst_phase_id']  ?? null;
    $pendingLogs = [];
    if ($userRoleName) {
        try {
            $pendingLogs = getPendingRequests($conn, $userPhaseId, $userRoleName);
        } catch (PDOException $e) {
            $pendingLogs = [];
        }
    }
    $pendingCount = count($pendingLogs);
    $latestPendingLogs = array_slice($pendingLogs, 0, 5); // Display top 5 in dropdown
}

// Global permission check for administrative UI
$userRoleForAccess = $_SESSION['wst_role_name'] ?? '';
$hasApprovalAccess = hasSettingsAccess($conn, $_SESSION['username'] ?? '', $userRoleForAccess);
?>

<!-- Client-side Employee Photo Fallback -->
<script>
function tryPhotoExtensions(employeeId, imgElement) {
    var extensions = ['jpeg', 'png', 'JPG', 'JPEG', 'PNG']; // Skip 'jpg' as default
    var baseUrl = 'http://10.2.0.8/lrnph/emp_photos/';

    // Initialize state
    if (typeof imgElement.dataset.tryIndex === 'undefined') {
        imgElement.dataset.tryIndex = 0;
    }

    var currentIndex = parseInt(imgElement.dataset.tryIndex);

    if (currentIndex < extensions.length) {
        // Try next
        imgElement.dataset.tryIndex = currentIndex + 1;
        imgElement.src = baseUrl + employeeId + '.' + extensions[currentIndex];
    } else {
        // Give up
        imgElement.onerror = null;
        imgElement.style.opacity = '0'; // hide image without collapsing space
        if (imgElement.nextElementSibling) {
            imgElement.nextElementSibling.style.display = 'block';
        }
    }
}
</script>
<header class="top-header align-items-center" style="margin-bottom: 20px;">
    <!-- Profile Left (Dropdown) -->
    <div class="dropdown">
        <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
            <div style="width: 48px; height: 48px; border-radius: 50%;">
                <?= getEmployeePhotoImg($currentUser['employee_id'], 'rounded-circle', $currentUser['full_name'], 'style="object-fit: cover; width: 100%; height: 100%;"', 'font-size: 2.5rem; color: #a1a1aa;') ?>
            </div>
            <div>
                <div class="fw-bold d-flex align-items-center gap-1" style="font-size: 1.1rem; color: #181a1f;">
                    <?= htmlspecialchars($currentUser['full_name']) ?> 
                    <ion-icon name="chevron-down-outline" class="text-muted" style="font-size: 0.9rem;"></ion-icon>
                </div>
                <div class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($currentUser['wst_role_name'] ?: $currentUser['position']) ?></div>
            </div>
        </div>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3" style="border-radius: 16px; font-weight: 500; min-width: 220px; padding: 10px; animation: fadeIn 0.2s ease-in-out;">
            <li><h6 class="dropdown-header text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">My Account</h6></li>
            <?php if ($hasApprovalAccess): ?>
            <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded" href="settings.php"><ion-icon name="settings-outline" class="fs-5 text-muted"></ion-icon> Settings</a></li>
            <li><hr class="dropdown-divider my-2"></li>
            <?php endif; ?>
            <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded text-danger" href="../auth/logout.php"><ion-icon name="log-out-outline" class="fs-5"></ion-icon> Logout</a></li>
        </ul>
    </div>
    
    <!-- Search and Notifications Right -->
    <div class="d-flex flex-grow-1 justify-content-end align-items-center gap-3">
        <div class="position-relative" style="max-width: 300px; width: 100%;">
            <ion-icon name="search-outline" class="position-absolute" style="left: 15px; top: 50%; transform: translateY(-50%); color: #8b929e; font-size: 1.2rem;"></ion-icon>
            <input type="text" id="globalSearchInput" class="form-control rounded-pill border-0 shadow-sm" placeholder="Search..." style="padding-left: 45px; height: 48px; font-weight: 500; background-color: #fff;">
        </div>
        <a href="guide.php" class="bg-white rounded-circle shadow-sm d-flex justify-content-center align-items-center text-decoration-none" style="width: 48px; height: 48px;" title="User Guide">
            <ion-icon name="help-circle-outline" style="font-size: 1.3rem; color: #181a1f;"></ion-icon>
        </a>
        <?php if ($hasApprovalAccess): ?>
        <div class="dropdown">
            <div class="bg-white rounded-circle shadow-sm d-flex justify-content-center align-items-center position-relative" data-bs-toggle="dropdown" aria-expanded="false" style="width: 48px; height: 48px; cursor: pointer;">
                <ion-icon name="notifications-outline" style="font-size: 1.3rem; color: #181a1f;"></ion-icon>
                <?php if ($pendingCount > 0): ?>
                    <span class="position-absolute translate-middle d-flex justify-content-center align-items-center rounded-circle fw-bold" id="topbarBellBadge" style="top: 10px; right: -5px; background-color: var(--accent-yellow); border: 2px solid white; width: 22px; height: 22px; font-size: 0.7rem; color: #000;">
                        <?= $pendingCount ?>
                    </span>
                <?php endif; ?>
            </div>

            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-0" style="border-radius: 20px; min-width: 360px; overflow: hidden; animation: fadeIn 0.2s ease-in-out;">
                <li class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold" style="color: #181a1f; font-size: 1.1rem;">Notifications</h6>
                    <?php if ($pendingCount > 0): ?>
                    <span class="badge rounded-pill bg-light text-dark" style="font-size: 0.75rem;"><ion-icon name="pulse-outline" class="me-1"></ion-icon><?= $pendingCount ?> New</span>
                    <?php endif; ?>
                </li>
                <div style="max-height: 250px; overflow-y: auto;" class="custom-scrollbar">
                    <?php if ($pendingCount > 0): ?>
                        <?php foreach ($latestPendingLogs as $plog): ?>
                            <li>
                                <a class="dropdown-item py-3 px-4 border-bottom d-flex align-items-start gap-3 dropdown-item-custom" href="../pages/supervisor.php" style="white-space: normal; transition: all 0.2s;">
                                    <div class="flex-shrink-0 position-relative">
                                        <div style="width: 45px; height: 45px;" class="rounded-circle shadow-sm">
                                            <?= getEmployeePhotoImg($plog['SubmitterEmployeeID'], 'rounded-circle', 'User', 'style="object-fit: cover; width: 100%; height: 100%; border: 2px solid white;"', 'font-size: 2.2rem; color: #a1a1aa;') ?>
                                        </div>
                                        <div class="position-absolute rounded-circle" style="width: 12px; height: 12px; background-color: var(--accent-yellow); bottom: 0; right: 0; border: 2px solid white; z-index: 20;"></div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold mb-1" style="font-size: 0.9rem; color: #181a1f;">
                                            <?= htmlspecialchars($plog['SubmitterName'] ?? 'Unknown') ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.8rem; line-height: 1.3;">
                                            New <strong><?= htmlspecialchars($plog['TypeName']) ?></strong> log requires approval. (<span style="color: #8b5cf6; font-weight: 500;"><?= htmlspecialchars($plog['AreaName']) ?></span>)
                                        </div>
                                        <div class="text-muted mt-2 d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                                            <ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars($plog['LogDate']) ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="p-4 text-center text-muted">
                            <ion-icon name="checkmark-done-circle-outline" style="font-size: 2.5rem; color: #a3e635; margin-bottom: 10px;"></ion-icon>
                            <div class="fw-bold" style="font-size: 0.95rem; color: #181a1f;">You're all caught up!</div>
                            <div style="font-size: 0.85rem;">No pending approvals at the moment.</div>
                        </li>
                    <?php endif; ?>
                </div>
                <?php if ($pendingCount > 0): ?>
                    <li class="p-3 text-center bg-white border-top">
                        <a href="../pages/supervisor.php" class="btn btn-sm w-100 rounded-pill fw-bold text-dark" style="background-color: #f4f6f8; font-size: 0.85rem; padding: 0.5rem;">View All Approvals</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</header>

<!-- SweetAlert2 Integration for Global App Modals -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                // Skip the "No logs found" empty state row if present
                if (row.querySelector('td[colspan]')) return;
                
                const text = row.textContent.toLowerCase();
                if (text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
<style>
/* Dropdown Hover Enhancements */
.dropdown-item-custom:hover {
    background-color: #f8fafc !important;
}
.dropdown-item:active {
    background-color: #f1f5f9;
}
</style>

<?php if (isset($_SESSION['success_msg'])): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: 'Awesome!',
            text: '<?= htmlspecialchars(addslashes($_SESSION['success_msg'])) ?>',
            icon: 'success',
            background: '#ffffff',
            color: '#181a1f',
            confirmButtonColor: '#202227',
            confirmButtonText: 'Continue',
            buttonsStyling: true,
            customClass: {
                popup: 'rounded-4 shadow-lg border-0',
                confirmButton: 'btn btn-dark rounded-pill px-4 fw-bold'
            }
        });
    });
</script>
<?php unset($_SESSION['success_msg']); endif; ?>

<?php if (isset($_SESSION['error_msg'])): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: 'Oops!',
            text: '<?= htmlspecialchars(addslashes($_SESSION['error_msg'])) ?>',
            icon: 'error',
            background: '#ffffff',
            color: '#181a1f',
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Try Again',
            customClass: {
                popup: 'rounded-4 shadow-lg border-0',
                confirmButton: 'btn btn-danger rounded-pill px-4 fw-bold'
            }
        });
    });
</script>
<?php unset($_SESSION['error_msg']); endif; ?>
