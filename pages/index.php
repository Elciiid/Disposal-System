<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';

require_once '../auth/auth_helpers.php';
$currentUser = getCurrentUser();

// Restrict access based on permissions
if (!hasPermission($conn, 'submit_logs')) {
    $_SESSION['error_msg'] = "Access Denied: You do not have permission to submit logs.";
    header("Location: dashboard.php");
    exit();
}

require_once '../utils/functions.php';
require_once '../api/approval_workflow.php';
$approvalCtx = getApprovalContext($conn);
$pendingCount = $approvalCtx['pendingCount'];
$pendingLogs = $approvalCtx['pendingLogs'];
$latestPendingLogs = $approvalCtx['latestPendingLogs'];

// Fetch all the options for our independent dropdowns using the generic function
$types        = fetchAllFromTable($conn, 'wst_LogTypes', 'TypeName');
$categories   = fetchAllFromTable($conn, 'wst_PCategories', 'CategoryName');
$areas        = fetchAllFromTable($conn, 'wst_Areas', 'AreaName');
$shifts       = fetchAllFromTable($conn, 'wst_Shifts');
$descriptions = fetchAllFromTable($conn, 'wst_PDescriptions', 'DescriptionName');
$phases       = fetchAllFromTable($conn, 'wst_Phases', 'PhaseName');
?>
<?php
$pageTitle = 'Production Waste Log';
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
                <h1 class="page-title" style="font-size: 3.2rem; letter-spacing: -1px;">Log Production Waste</h1>
                <p class="page-subtitle text-muted mt-1">Record a new waste or transfer entry quickly.</p>
            </div>
            <div class="d-flex align-items-center gap-3 pb-2">
                <span class="text-muted fw-medium" style="font-size: 0.95rem;"><?= date('d F, Y') ?></span>
            </div>
        </div>

        <form action="../api/save_log.php" method="POST" class="w-100 flex-grow-1 d-flex flex-column gap-3">
            <div class="dashboard-grid w-100 flex-grow-1">
                
                <!-- Left Column: Classification -->
                <div class="data-card position-relative text-start shadow-sm d-flex flex-column" style="border-radius: 30px; border: 1px solid rgba(0,0,0,0.03); background: #ffffff; padding: 40px;">
                    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <ion-icon name="options-outline" style="font-size: 1.6rem; color: #181a1f;"></ion-icon>
                        </div>
                        <div>
                            <h3 class="fs-5 fw-bold mb-0" style="color: #181a1f;">Classification Settings</h3>
                            <div class="text-muted" style="font-size: 0.85rem;">Define where and to what phase</div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label"><ion-icon name="calendar-outline" class="me-1"></ion-icon> Date & Time</label>
                            <input type="text" class="form-control" name="LogDate" id="logDateStatic" value="<?= date('Y-m-d | h:i A', floor(time() / 1800) * 1800) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><ion-icon name="funnel-outline" class="me-1"></ion-icon> Type</label>
                            <select class="form-select" name="TypeID" id="typeSelect" required>
                                <option value="">Select Type...</option>
                                <?php foreach($types as $type): ?>
                                    <option value="<?= $type['TypeID'] ?>" data-phase-id="<?= htmlspecialchars($type['PhaseID'] ?? '') ?>"><?= htmlspecialchars($type['TypeName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4 d-none" id="otherTypeContainer">
                        <div class="col-12">
                            <label class="form-label text-warning"><ion-icon name="alert-circle-outline" class="me-1"></ion-icon> Others Remarks</label>
                            <input type="text" class="form-control border-warning" name="OtherTypeRemark" id="otherTypeRemark" placeholder="Specify the type of log (e.g., Trial Run, Special Handling)..." style="background-color: #fffbeb;">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label"><ion-icon name="git-merge-outline" class="me-1"></ion-icon> Production Phase</label>
                            <?php
                                // Auto-set phase from logged-in user's assignment
                                $userPhaseId = $_SESSION['wst_phase_id'] ?? null;
                                $phaseAssigned = !empty($userPhaseId);
                            ?>
                            <select class="form-select" id="phaseSelect" <?= $phaseAssigned ? 'disabled' : 'name="PhaseID" required' ?>>
                                <option value="">Select Phase...</option>
                                <?php foreach($phases as $phase): ?>
                                    <option value="<?= $phase['PhaseID'] ?>" <?= ($phase['PhaseID'] == $userPhaseId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($phase['PhaseName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($phaseAssigned): ?>
                                <input type="hidden" name="PhaseID" id="hiddenPhaseID" value="<?= (int)$userPhaseId ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><ion-icon name="time-outline" class="me-1"></ion-icon> Shift</label>
                            <select class="form-select" name="ShiftID" required>
                                <option value="">Select Shift...</option>
                                <?php foreach($shifts as $shift): ?>
                                    <option value="<?= $shift['ShiftID'] ?>"><?= htmlspecialchars($shift['ShiftName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label"><ion-icon name="location-outline" class="me-1"></ion-icon> Area</label>
                            <?php 
                            $userAreaId = $_SESSION['wst_area_id'] ?? '';
                            $areaAssigned = !empty($userAreaId);
                            ?>
                            <select class="form-select" <?= $areaAssigned ? 'disabled' : 'name="AreaID" required' ?>>
                                <option value="">Select Area...</option>
                                <?php foreach($areas as $area): ?>
                                    <option value="<?= $area['AreaID'] ?>" <?= ($area['AreaID'] == $userAreaId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($area['AreaName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($areaAssigned): ?>
                                <!-- Hidden input to send AreaID when select is disabled -->
                                <input type="hidden" name="AreaID" value="<?= htmlspecialchars($userAreaId) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><ion-icon name="grid-outline" class="me-1"></ion-icon> Category</label>
                            <select class="form-select" name="CategoryID" required>
                                <option value="">Select Category...</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-auto d-flex align-items-center justify-content-between p-3 rounded-4" style="background-color: #f8fafc; border: 1px dashed #e2e8f0;">
                        <div class="d-flex align-items-center gap-3">
                            <ion-icon name="shield-checkmark" style="color: #a3e635; font-size: 2rem;"></ion-icon>
                            <span class="text-secondary fw-bold" style="font-size: 0.9rem; color: #64748b;">Please double check classification entries. Accuracy ensures better metrics.</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Product, Quantities, and Submission -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <!-- Top Card: Product details & Qty -->
                    <div class="data-card position-relative text-start shadow-sm" style="border-radius: 30px; border: 1px solid rgba(0,0,0,0.03); background: #ffffff; padding: 40px;">
                        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <ion-icon name="cube-outline" style="font-size: 1.6rem; color: #8b5cf6;"></ion-icon>
                            </div>
                            <div>
                                <h3 class="fs-5 fw-bold mb-0" style="color: #181a1f;">Measurements</h3>
                                <div class="text-muted" style="font-size: 0.85rem;">Specify product and amounts</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><ion-icon name="search-outline" class="me-1"></ion-icon> Product Description</label>
                            <input class="form-control" list="productOptions" id="productSearch" placeholder="Type to search product description..." required autocomplete="off">
                            <datalist id="productOptions">
                                <?php foreach($descriptions as $desc): ?>
                                    <option data-id="<?= $desc['DescriptionID'] ?>" value="<?= htmlspecialchars($desc['DescriptionName']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" name="DescriptionID" id="hiddenDescriptionID" required>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label"><ion-icon name="scale-outline" class="me-1"></ion-icon> Weight Appx (KG)</label>
                                <div class="position-relative">
                                    <input type="number" step="0.01" class="form-control" name="KG" placeholder="0.00" style="padding-right: 50px;">
                                    <span class="position-absolute text-muted fw-bold" style="right: 15px; top: 11px; font-size: 0.85rem;">KG</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Card: Reason and Submit (Dark styling for emphasis) -->
                    <div class="data-card position-relative text-start shadow-sm d-flex flex-column flex-grow-1" style="border-radius: 30px; background-color: #202227; color: white; padding: 40px;">
                        <div class="mb-4 flex-grow-1 d-flex flex-column">
                            <label class="form-label text-white"><ion-icon name="document-text-outline" class="me-1" style="color: #a1a1aa;"></ion-icon> Reason for Log</label>
                            <textarea class="form-control flex-grow-1 dark-textarea" name="Reason" rows="3" placeholder="Explain the reason for waste/transfer... Avoid generic reasons." style="background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; resize: none;" required></textarea>
                        </div>

                        <div class="mt-auto d-flex justify-content-end align-items-center flex-wrap pt-2 gap-3">
                            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" style="background-color: var(--accent-yellow); color: #000; box-shadow: 0 4px 15px rgba(209, 244, 58, 0.2); padding: 0.85rem 2rem;">
                                Submit Log <ion-icon name="arrow-forward-outline"></ion-icon>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<?php require_once '../components/scripts.php'; ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const productSearch = document.getElementById('productSearch');
        const hiddenId = document.getElementById('hiddenDescriptionID');
        const datalistOptions = document.getElementById('productOptions').options;

        productSearch.addEventListener('input', function() {
            hiddenId.value = ''; // Reset ID if user changes text
            for (let i = 0; i < datalistOptions.length; i++) {
                if (datalistOptions[i].value === productSearch.value) {
                    hiddenId.value = datalistOptions[i].getAttribute('data-id');
                    break;
                }
            }
        });
        
        // Validation check before submittion to ensure a valid choice from the list
        document.querySelector('form').addEventListener('submit', function(e) {
            if (hiddenId.value === '') {
                e.preventDefault();
                alert('Please select a valid product description from the search list.');
            }
        });
        
        // Type dropdown phase filtering
        const typeSelect = document.querySelector('select[name="TypeID"]');
        const phaseSelect = document.getElementById('phaseSelect');
        const hiddenPhaseId = document.getElementById('hiddenPhaseID');
        const typeOptions = Array.from(typeSelect.options);
        
        function filterTypes() {
            const selectedPhaseId = hiddenPhaseId ? hiddenPhaseId.value : phaseSelect.value;
            let currentTypeValue = typeSelect.value;
            let typeStillVisible = false;
            
            typeSelect.innerHTML = '';
            
            typeOptions.forEach(option => {
                const phaseId = option.getAttribute('data-phase-id');
                // Auto-show if no phase is selected (!selectedPhaseId), or if it applies to all phases (empty phaseId), OR if it matches
                if (!selectedPhaseId || !phaseId || phaseId === selectedPhaseId || option.value === '') {
                    // clone node to avoid ref issues
                    typeSelect.appendChild(option.cloneNode(true));
                    if (option.value === currentTypeValue) typeStillVisible = true;
                }
            });
            
            if (!typeStillVisible) {
                typeSelect.value = '';
            } else {
                typeSelect.value = currentTypeValue;
            }
        }
        
        if (phaseSelect) {
            phaseSelect.addEventListener('change', filterTypes);
            // trigger on load
            filterTypes();
        }

        // Initialize Flatpickr for LogDate
        flatpickr("#logDateStatic", {
            enableTime: true,
            dateFormat: "Y-m-d | h:i K",
            time_24hr: false,
            minuteIncrement: 30,
            defaultDate: document.getElementById('logDateStatic').value
        });
        const typeSelectInput = document.getElementById('typeSelect');
        const otherTypeContainer = document.getElementById('otherTypeContainer');
        const otherTypeRemarkInput = document.getElementById('otherTypeRemark');

        typeSelectInput.addEventListener('change', function() {
            const selectedText = this.options[this.selectedIndex].text;
            if (selectedText === 'Others') {
                otherTypeContainer.classList.remove('d-none');
                otherTypeRemarkInput.setAttribute('required', 'required');
            } else {
                otherTypeContainer.classList.add('d-none');
                otherTypeRemarkInput.removeAttribute('required');
                otherTypeRemarkInput.value = '';
            }
        });
    });
</script>
</body>
</html>
