<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../api/approval_workflow.php';

$currentUser = getCurrentUser();

// Disallow access if they don't have the 'view_history' permission
$hasAccess = hasPermission($conn, 'view_history') || hasSettingsAccess($conn, $_SESSION['username'] ?? '', $_SESSION['wst_role_name'] ?? '');
if (!$hasAccess) {
    header("Location: dashboard.php");
    exit();
}

$approvalCtx = getApprovalContext($conn);
$pendingCount = $approvalCtx['pendingCount'];
$pendingLogs = $approvalCtx['pendingLogs'];
$latestPendingLogs = $approvalCtx['latestPendingLogs'];

require_once '../utils/functions.php';

// Collect URL filters for history
$filters = [
    'startDate'  => $_GET['startDate'] ?? '',
    'endDate'    => $_GET['endDate'] ?? '',
    'limit'      => $_GET['limit'] ?? '100',
    'phaseId'    => $_GET['phaseId'] ?? '',
    'shiftId'    => $_GET['shiftId'] ?? '',
    'areaId'     => $_GET['areaId'] ?? '',
    'typeId'     => $_GET['typeId'] ?? '',
    'categoryId' => $_GET['categoryId'] ?? '',
    'status'     => $_GET['status'] ?? 'All'
];

// Fetch master data for dropdowns
$phases     = fetchAllFromTable($conn, 'wst_Phases', 'PhaseName');
$shifts     = fetchAllFromTable($conn, 'wst_Shifts', 'ShiftName');
$areas      = fetchAllFromTable($conn, 'wst_Areas', 'AreaName');
$types      = fetchAllFromTable($conn, 'wst_LogTypes', 'TypeName');
$categories = fetchAllFromTable($conn, 'wst_PCategories', 'CategoryName');

// Fetch logs with the dynamic status filter
$logs = getWasteLogs($conn, $filters['status'], $filters);
?>
<?php
$pageTitle = 'History Logs - Waste Logs';
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

        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h1 class="page-title" style="font-size: 3.2rem; letter-spacing: -1px;">History Logs</h1>
                <p class="page-subtitle text-muted mt-1">View all approved and declined production waste entries.</p>
            </div>
            <div class="d-flex align-items-center gap-3 pb-2">
                <span class="text-muted fw-medium" style="font-size: 0.95rem;"><?= date('d F, Y') ?></span>
                
                <div class="dropdown">
                    <button class="btn bg-white rounded-pill shadow-sm fw-bold px-4 py-2 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.95rem;">
                        Filter Logs <ion-icon name="filter-outline"></ion-icon>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 filter-dropdown" style="animation: fadeIn 0.2s ease-in-out;">
                        <h6 class="fw-bold mb-3" style="color: #181a1f; letter-spacing: -0.2px;">Filter Options</h6>
                        <form method="GET" action="history.php">
                            <div class="filter-scroll-container">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Start Date</label>
                                        <input type="date" class="form-control form-control-sm" name="startDate" value="<?= htmlspecialchars($filters['startDate']) ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">End Date</label>
                                        <input type="date" class="form-control form-control-sm" name="endDate" value="<?= htmlspecialchars($filters['endDate']) ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Status</label>
                                        <select class="form-select form-select-sm" name="status">
                                            <option value="All" <?= $filters['status'] == 'All' ? 'selected' : '' ?>>All Status</option>
                                            <option value="Pending" <?= $filters['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Approved" <?= $filters['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="Declined" <?= $filters['status'] == 'Declined' ? 'selected' : '' ?>>Declined</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Phase</label>
                                        <select class="form-select form-select-sm" name="phaseId">
                                            <option value="">All Phases</option>
                                            <?php foreach ($phases as $p): ?>
                                                <option value="<?= $p['PhaseID'] ?>" <?= $filters['phaseId'] == $p['PhaseID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($p['PhaseName']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Shift</label>
                                        <select class="form-select form-select-sm" name="shiftId">
                                            <option value="">All Shifts</option>
                                            <?php foreach ($shifts as $s): ?>
                                                <option value="<?= $s['ShiftID'] ?>" <?= $filters['shiftId'] == $s['ShiftID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($s['ShiftName']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Area</label>
                                        <select class="form-select form-select-sm" name="areaId">
                                            <option value="">All Areas</option>
                                            <?php foreach ($areas as $a): ?>
                                                <option value="<?= $a['AreaID'] ?>" <?= $filters['areaId'] == $a['AreaID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($a['AreaName']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Type</label>
                                        <select class="form-select form-select-sm" name="typeId">
                                            <option value="">All Types</option>
                                            <?php foreach ($types as $t): ?>
                                                <option value="<?= $t['TypeID'] ?>" <?= $filters['typeId'] == $t['TypeID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($t['TypeName']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Category</label>
                                        <select class="form-select form-select-sm" name="categoryId">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $c): ?>
                                                <option value="<?= $c['CategoryID'] ?>" <?= $filters['categoryId'] == $c['CategoryID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['CategoryName']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Page Limit</label>
                                    <select class="form-select form-select-sm" name="limit">
                                        <option value="50" <?= $filters['limit'] == '50' ? 'selected' : '' ?>>Top 50</option>
                                        <option value="100" <?= $filters['limit'] == '100' ? 'selected' : '' ?>>Top 100</option>
                                        <option value="500" <?= $filters['limit'] == '500' ? 'selected' : '' ?>>Top 500</option>
                                        <option value="1000" <?= $filters['limit'] == '1000' ? 'selected' : '' ?>>Top 1000</option>
                                        <option value="999999" <?= $filters['limit'] == '999999' ? 'selected' : '' ?>>All Logs</option>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="filter_applied" value="1">
                            <div class="d-flex gap-2">
                                <a href="history.php" class="btn btn-light w-50 py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.9rem; border-radius: 12px; border: 1px solid #e2e8f0; color: #64748b;">
                                    Clear <ion-icon name="close-outline"></ion-icon>
                                </a>
                                <button type="submit" class="btn btn-primary w-50 py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.9rem; border-radius: 12px; background-color: var(--accent-yellow); color: black; border: none;">
                                    Apply <ion-icon name="checkmark-outline"></ion-icon>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (hasPermission($conn, 'export_csv')): ?>
                <a href="../api/export_logs.php?<?= http_build_query($_GET) ?>" class="btn rounded-pill shadow-sm fw-bold px-4 py-2 d-flex align-items-center gap-2 csv-btn-static" style="font-size: 0.95rem; background: white; border: 1px solid rgba(0,0,0,0.1); color: #181a1f;">
                    Export CSV <ion-icon name="download-outline"></ion-icon>
                </a>
                <?php endif; ?>

            </div>

        </div>

        <div class="data-card position-relative text-start shadow-sm flex-grow-1 d-flex flex-column" style="border-radius: 30px; border: 1px solid rgba(0,0,0,0.03); background: #ffffff; padding: 40px;">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <ion-icon name="time-outline" style="font-size: 1.6rem; color: #181a1f;"></ion-icon>
                </div>
                <div>
                    <h3 class="fs-5 fw-bold mb-0" style="color: #181a1f;">Log Archive</h3>
                    <div class="text-muted" style="font-size: 0.85rem;">Past resolved entries</div>
                </div>
            </div>
            <table class="table table-borderless">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Submitted By</th>
                        <th>Approved By</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach($logs as $log): ?>
                            <tr id="row-<?= $log['LogID'] ?>">
                                <td class="text-nowrap">
                                    <span class="fw-bold"><?= date('M d, Y', strtotime($log['LogDate'])) ?></span>
                                    <span class="text-muted mx-1">•</span>
                                    <span class="text-secondary"><?= date('h:i A', strtotime($log['LogDate'])) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div style="width: 40px; height: 40px; flex-shrink: 0;" class="rounded-circle shadow-sm border border-light">
                                            <?= getEmployeePhotoImg($log['SubmitterEmployeeID'], 'rounded-circle', $log['SubmitterName'] ?? 'User', 'style="object-fit: cover; width: 100%; height: 100%;"', 'font-size: 2rem; color: #a1a1aa;') ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold" style="font-size: 0.9em; color: #181a1f;">
                                                <?= htmlspecialchars($log['SubmitterName'] ?: 'Unknown') ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75em; letter-spacing: 0.5px; text-transform: uppercase;">
                                                <?= htmlspecialchars($log['SubmittedBy'] ?: 'Unknown') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($log['ApproverName'])): ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <div style="width: 40px; height: 40px; flex-shrink: 0;" class="rounded-circle shadow-sm border border-light">
                                            <?= getEmployeePhotoImg($log['ApproverEmployeeID'], 'rounded-circle', $log['ApproverName'], 'style="object-fit: cover; width: 100%; height: 100%;"', 'font-size: 2rem; color: #a1a1aa;') ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold" style="font-size: 0.9em; color: #181a1f;">
                                                <?= htmlspecialchars($log['ApproverName'] ?: 'Unknown') ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75em; letter-spacing: 0.5px; text-transform: uppercase;">
                                                <?= htmlspecialchars($log['ApproverBiometricsID'] ?: 'Unknown') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted fst-italic">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <?php 
                                    $status = $log['ApprovalStatus'] ?? 'Unknown'; 
                                    $badgeStyle = 'background-color: #f1f5f9; color: #475569;';
                                    if ($status === 'Approved') $badgeStyle = 'background-color: var(--accent-green); color: #000; font-weight: 600;';
                                    if ($status === 'Declined') $badgeStyle = 'background-color: #fee2e2; color: #ef4444; font-weight: 600;';
                                ?>
                                <td class="text-center align-middle">
                                    <span class="badge rounded-pill px-3 py-2 border border-light" style="<?= $badgeStyle ?>; font-size: 0.85rem; letter-spacing: 0.3px;">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td class="text-end align-middle">
                                    <button type="button" class="btn btn-sm rounded-pill shadow-sm fw-bold d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#detailsModal" data-log="<?= htmlspecialchars(json_encode($log)) ?>" style="background-color: #181a1f; color: white; padding: 0.4rem 1.2rem; border: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 0.125rem 0.25rem rgba(0,0,0,0.075)';">
                                        <ion-icon name="eye-outline" style="color: var(--accent-yellow); font-size: 1.1rem;"></ion-icon> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No history logs found!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
      <div class="modal-header border-0 bg-light px-4 py-3">
        <h5 class="modal-title fw-bold" style="color: #181a1f;"><ion-icon name="document-text-outline" class="me-2"></ion-icon>Log Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 py-4">
        <div class="row g-3">
            <div class="col-6">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Shift</span>
                <div id="modalShift" class="fw-medium mt-1"></div>
            </div>
            <div class="col-6">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Area</span>
                <div id="modalArea" class="fw-medium mt-1"></div>
            </div>
            <div class="col-6">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Phase</span>
                <div id="modalPhase" class="fw-medium mt-1"></div>
            </div>
            <div class="col-6">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Type</span>
                <div id="modalType" class="fw-medium mt-1"></div>
            </div>
            <div class="col-6">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Category</span>
                <div id="modalCategory" class="fw-medium mt-1"></div>
            </div>
            <div class="col-6">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Product</span>
                <div id="modalProduct" class="fw-medium mt-1"></div>
            </div>
            <div class="col-12 mt-3 pt-3 border-top border-light">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Quantity</span>
                <div id="modalQty" class="fw-bold fs-5 mt-1" style="color: #6366f1;"></div>
            </div>
            <div class="col-12 mt-3 p-3 rounded-3 d-none" style="background-color: #fffbeb; border: 1px solid #fde68a;" id="modalOtherTypeContainer">
                <span class="text-warning text-uppercase" style="font-size: 0.75rem; font-weight: 700;">Others Remarks</span>
                <div id="modalOtherTypeRemark" class="mt-1 fw-bold" style="font-size: 0.95rem; color: #92400e;"></div>
            </div>
            <div class="col-12 mt-3 p-3 rounded-3" style="background-color: #f8fafc;">
                <span class="text-muted text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Remarks / Reason</span>
                <div id="modalReason" class="mt-1" style="font-size: 0.95rem; line-height: 1.5;"></div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../components/scripts.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailsModal = document.getElementById('detailsModal');
    if (detailsModal) {
        detailsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const log = JSON.parse(button.getAttribute('data-log'));

            document.getElementById('modalShift').textContent = log.ShiftName || 'N/A';
            document.getElementById('modalArea').textContent = log.AreaName || 'N/A';
            document.getElementById('modalPhase').textContent = log.PhaseName || 'N/A';
            document.getElementById('modalType').textContent = log.TypeName || 'N/A';
            document.getElementById('modalCategory').textContent = log.CategoryName || 'N/A';
            document.getElementById('modalProduct').textContent = log.DescriptionName || 'N/A';
            
            let qtyText = '';
            if (log.PCS) qtyText += log.PCS + ' pcs ';
            if (log.KG) {
                let kgDisplay = String(log.KG);
                if (kgDisplay.startsWith('.')) kgDisplay = '0' + kgDisplay;
                qtyText += (log.PCS ? '| ' : '') + kgDisplay + ' kg';
            }
            document.getElementById('modalQty').textContent = qtyText || '0';

            // Handle Other Type Remark
            const otherContainer = document.getElementById('modalOtherTypeContainer');
            if (log.OtherTypeRemark) {
                otherContainer.classList.remove('d-none');
                document.getElementById('modalOtherTypeRemark').textContent = log.OtherTypeRemark;
            } else {
                otherContainer.classList.add('d-none');
            }
            
            document.getElementById('modalReason').innerHTML = log.Reason ? log.Reason.replace(/\n/g, '<br>') : '<span class="text-muted fst-italic">No reason provided</span>';
        });
    }
});
</script>
</body>
</html>
