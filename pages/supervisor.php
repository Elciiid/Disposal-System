<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../api/approval_workflow.php';

$currentUser = getCurrentUser();

// Ensure user is logged in
if (!$currentUser || empty($currentUser['username'])) {
    header("Location: ../pages/login.php");
    exit();
}

require_once '../utils/functions.php';

// Get user's role and phase from session
$userRoleName = $_SESSION['wst_role_name'] ?? null;
$userPhaseId  = $_SESSION['wst_phase_id'] ?? null;

// Collect URL filters for approvals
$filters = [
    'startDate'  => $_GET['startDate'] ?? '',
    'endDate'    => $_GET['endDate'] ?? '',
    'limit'      => $_GET['limit'] ?? '100',
    'shiftId'    => $_GET['shiftId'] ?? '',
    'areaId'     => $_GET['areaId'] ?? '',
    'typeId'     => $_GET['typeId'] ?? '',
    'categoryId' => $_GET['categoryId'] ?? ''
];

// Fetch master data for dropdowns
$shifts     = fetchAllFromTable($conn, 'wst_Shifts', 'ShiftName');
$areas      = fetchAllFromTable($conn, 'wst_Areas', 'AreaName');
$types      = fetchAllFromTable($conn, 'wst_LogTypes', 'TypeName');
$categories = fetchAllFromTable($conn, 'wst_PCategories', 'CategoryName');

// Determine which step this user handles based on permissions
[$userStepNumber, $userStepConfig] = getStepForUser($conn);

try {
    // Fetch pending requests ONCE — shared by page, topbar, and sidebar
    $approvalCtx = getApprovalContext($conn);
    $pendingLogs       = $approvalCtx['pendingLogs'];
    $pendingCount      = $approvalCtx['pendingCount'];
    $latestPendingLogs = $approvalCtx['latestPendingLogs'];
    // Fetch specifically for the main table using the applied filters
    $logs              = getPendingRequests($conn, $userPhaseId, null, $filters);
} catch(PDOException $e) {
    require_once '../utils/functions.php';
    handleSystemError("Error fetching logs: " . $e->getMessage());
}
?>
<?php
$pageTitle = 'Supervisor Dashboard - Waste Logs';
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
                <h1 class="page-title" style="font-size: 3.2rem; letter-spacing: -1px;">Waste Log Approvals</h1>
                <p class="page-subtitle text-muted mt-1">
                    <?php 
                        $allSteps = getAuthorizedStepsForUser($conn);
                        if (!empty($allSteps)): 
                            $stepLabels = array_map(function($s) { return "Step $s"; }, $allSteps);
                            echo implode(' & ', $stepLabels) . " — " . htmlspecialchars($userRoleName) . " Review";
                        ?>
                        <?php if ($userStepConfig['scope'] === 'phase' && !empty($userPhaseId)): ?>
                            <span class="badge bg-light text-dark border ms-2" style="font-size: 0.8rem;">Phase-bound</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border ms-2" style="font-size: 0.8rem;">Global Reviewer</span>
                        <?php endif; ?>
                    <?php else: ?>
                        You are not part of the approval chain.
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex align-items-center gap-3 pb-2">
                <span class="text-muted fw-medium" style="font-size: 0.95rem;"><?= date('d F, Y') ?></span>
                
                <div class="dropdown">
                    <button class="btn bg-white rounded-pill shadow-sm fw-bold px-4 py-2 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.95rem;">
                        Options <ion-icon name="options-outline"></ion-icon>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 filter-dropdown" style="animation: fadeIn 0.2s ease-in-out;">
                        <h6 class="fw-bold mb-3" style="color: #181a1f; letter-spacing: -0.2px;">Filter Options</h6>
                        <form method="GET" action="supervisor.php">
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
                                    <div class="col-6">
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
                                    <label class="form-label text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Show Entries</label>
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
                                <a href="supervisor.php" class="btn btn-light w-50 py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.9rem; border-radius: 12px; border: 1px solid #e2e8f0; color: #64748b;">
                                    Clear <ion-icon name="close-outline"></ion-icon>
                                </a>
                                <button type="submit" class="btn btn-primary w-50 py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.9rem; border-radius: 12px; background-color: var(--accent-yellow); color: black; border: none;">
                                    Apply <ion-icon name="checkmark-outline"></ion-icon>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <div class="data-card position-relative text-start shadow-sm flex-grow-1 d-flex flex-column" style="border-radius: 30px; border: 1px solid rgba(0,0,0,0.03); background: #ffffff; padding: 40px;">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <ion-icon name="shield-checkmark-outline" style="font-size: 1.6rem; color: #8b5cf6;"></ion-icon>
                </div>
                <div>
                    <h3 class="fs-5 fw-bold mb-0" style="color: #181a1f;">Pending Approvals</h3>
                    <div class="text-muted" style="font-size: 0.85rem;">Review and decide on new transfers/waste logs</div>
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
                                    $status = $log['ApprovalStatus'] ?? 'Pending'; 
                                    $badgeStyle = 'background-color: var(--accent-yellow); color: #000; font-weight: 600;';
                                ?>
                                <td class="text-center align-middle">
                                    <span class="badge rounded-pill px-3 py-2 border border-light" id="status-<?= $log['LogID'] ?>" style="<?= $badgeStyle ?>; font-size: 0.85rem; letter-spacing: 0.3px;">
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
                            <td colspan="12" class="text-center py-5 text-muted">🎉 No waste logs found!</td>
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
      <div class="modal-footer border-0 bg-light px-4 py-3 justify-content-between" id="modalActionFooter">
        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">Action Required:</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm shadow-sm rounded-pill text-danger bg-white border fw-bold border-danger-subtle px-4 py-2" id="modalBtnDecline">Decline</button>
            <button type="button" class="btn btn-sm shadow-sm rounded-pill fw-bold px-4 py-2" style="background-color: var(--accent-green); color: black;" id="modalBtnApprove">Approve</button>
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
            
            const status = log.ApprovalStatus || 'Pending';
            const actionFooter = document.getElementById('modalActionFooter');
            if (status === 'Pending') {
                actionFooter.style.display = 'flex';
                document.getElementById('modalBtnApprove').onclick = function() {
                    bootstrap.Modal.getInstance(detailsModal).hide();
                    updateStatus(log.LogID, 'approve');
                };
                document.getElementById('modalBtnDecline').onclick = function() {
                    bootstrap.Modal.getInstance(detailsModal).hide();
                    updateStatus(log.LogID, 'reject');
                };
            } else {
                actionFooter.style.display = 'none';
            }
        });
    }
});

let pendingCount = <?= $pendingCount ?>;

function updateStatus(logId, action) {
    const actionLabel = action === 'approve' ? 'Approve' : 'Decline';
    const pastLabel   = action === 'approve' ? 'Approved' : 'Rejected';

    let swalConfig = {
        title: 'Confirm Action',
        text: `Are you sure you want to ${actionLabel} this log?`,
        icon: 'warning',
        showCancelButton: true,
        background: '#ffffff',
        color: '#181a1f',
        confirmButtonColor: action === 'approve' ? '#202227' : '#ef4444',
        cancelButtonColor: '#f1f5f9',
        confirmButtonText: `Yes, ${actionLabel} it!`,
        cancelButtonText: '<span style="color: #475569; font-weight: 600;">Cancel</span>',
        buttonsStyling: true,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
            confirmButton: `btn ${action === 'approve' ? 'btn-dark' : 'btn-danger'} rounded-pill px-4 fw-bold mx-2`,
            cancelButton: 'btn rounded-pill px-4 fw-bold border-0 mx-2'
        }
    };

    if (action === 'reject') {
        swalConfig.input = 'textarea';
        swalConfig.inputPlaceholder = 'Please briefly explain why this is being declined...';
        swalConfig.inputAttributes = { 'aria-label': 'Reason for declining' };
        swalConfig.preConfirm = (reason) => {
            if (!reason || reason.trim() === '') {
                Swal.showValidationMessage('You must provide a reason for declining.');
            }
            return reason;
        };
    }

    Swal.fire(swalConfig).then((result) => {
        if (result.isConfirmed) {
            let bodyData = 'requestId=' + logId + '&action=' + action;
            if (action === 'reject' && result.value) {
                bodyData += '&reason=' + encodeURIComponent(result.value);
            }

            fetch('../api/approve_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: action === 'approve' ? 'Approved!' : 'Declined',
                        text: data.message,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });

                    // Update the badge
                    const badge = document.getElementById('status-' + logId);
                    badge.textContent = pastLabel;
                    badge.className = 'badge rounded-pill px-3 py-2 border border-light';
                    if (action === 'approve') {
                        badge.style.cssText = 'background-color: var(--accent-green); color: #000; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.3px;';
                    } else {
                        badge.style.cssText = 'background-color: #fee2e2; color: #ef4444; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.3px;';
                    }
                    
                    // Remove the buttons
                    const actionTd = badge.parentElement.nextElementSibling;
                    actionTd.innerHTML = '<span class="text-muted fw-bold" style="font-size: 0.8rem;">Reviewed</span>';

                    // Decrement global pending count
                    pendingCount--;

                    // Update topbar bell badge
                    const bellBadge = document.getElementById('topbarBellBadge');
                    if (bellBadge) {
                        if (pendingCount > 0) {
                            bellBadge.textContent = pendingCount;
                        } else {
                            bellBadge.remove();
                        }
                    }
                    
                    // Update sidebar badge
                    const pendingBadge = document.getElementById('pendingBadge');
                    if (pendingBadge) {
                        if (pendingCount > 0) {
                            pendingBadge.textContent = pendingCount;
                        } else {
                            pendingBadge.remove();
                        }
                    }
                } else {
                    Swal.fire({
                        title: 'Access Denied',
                        text: data.message || 'You are not authorized to perform this action.',
                        icon: 'error',
                        background: '#ffffff',
                        customClass: {
                            popup: 'rounded-4 shadow-lg border-0',
                            confirmButton: 'btn btn-danger rounded-pill px-4 fw-bold'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An unexpected error occurred.',
                    icon: 'error',
                    background: '#ffffff',
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0',
                        confirmButton: 'btn btn-danger rounded-pill px-4 fw-bold'
                    }
                });
            });
        }
    });
}
</script>
</body>
</html>
