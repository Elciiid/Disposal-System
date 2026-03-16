<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/access_control.php';
require_once '../utils/functions.php';

$currentUser = getCurrentUser();
requireSupervisorAccess($conn, $currentUser);

require_once '../api/approval_workflow.php';
$approvalCtx = getApprovalContext($conn);
$pendingCount = $approvalCtx['pendingCount'];
$pendingLogs = $approvalCtx['pendingLogs'];
$latestPendingLogs = $approvalCtx['latestPendingLogs'];

// Fetch essential data for modals and setup
try {
    $roles         = fetchAllFromTable($conn, 'wst_Roles', 'RoleName');
    $phases        = fetchAllFromTable($conn, 'wst_Phases', 'PhaseName');
    $areas         = fetchAllFromTable($conn, 'wst_Areas', 'AreaName');

    // We will fetch the large lists (Users, Descriptions, etc.) via AJAX to prevent lagginess
} catch (Exception $e) {
    require_once '../utils/functions.php';
    handleSystemError("Error fetching settings data: " . $e->getMessage());
}
?>
<?php
$pageTitle = 'System Settings - Waste Logs';
$extraCSS = ['supervisor.css', 'settings.css'];
require_once '../components/header.php';
require_once '../utils/ui_helpers.php';
?>
<body class="settings-page-active">

<div class="dashboard-wrapper">
    <?php include '../components/sidebar.php'; ?>

    <main class="main-content">
        <?php include '../components/topbar.php'; ?>

        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h1 class="page-title" style="font-size: 3.2rem; letter-spacing: -1px;">System Settings</h1>
                <p class="page-subtitle text-muted mt-1">Configure Master Data and Personnel Assignments</p>
            </div>
        </div>

        <!-- Navigation Tabs — Pill Group with Sliding Indicator -->
        <div class="settings-tab-nav" id="tabNav">
            <div class="tab-indicator" id="tabIndicator"></div>
            <button class="settings-tab-btn active" data-tab="personnel" onclick="switchTab('personnel')">
                <ion-icon name="people-outline" class="me-1" style="font-size:1rem;vertical-align:-2px;"></ion-icon> Personnel
            </button>
            <button class="settings-tab-btn" data-tab="products" onclick="switchTab('products')">
                <ion-icon name="cube-outline" class="me-1" style="font-size:1rem;vertical-align:-2px;"></ion-icon> Product Master
            </button>
            <button class="settings-tab-btn" data-tab="logs" onclick="switchTab('logs')">
                <ion-icon name="document-text-outline" class="me-1" style="font-size:1rem;vertical-align:-2px;"></ion-icon> Log Config
            </button>
            <button class="settings-tab-btn" data-tab="org" onclick="switchTab('org')">
                <ion-icon name="business-outline" class="me-1" style="font-size:1rem;vertical-align:-2px;"></ion-icon> Organizational
            </button>
        </div>

        <!-- Personnel Tab -->
        <div id="tab-personnel" class="settings-tab-content h-100">
            <div class="row g-4">
                <div class="col-lg-8 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>App Staff & Roles</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter staff..." onkeyup="filterTable(this, 'container-appUsers')">
                                    </div>
                                    <button class="btn-add-new" onclick="showAddUserModal()">
                                        <ion-icon name="person-add-outline"></ion-icon> Assign Staff
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-appUsers" data-lazy-table="wst_Users">
                                <!-- Skeleton Loader -->
                                <div class="table-content-wrapper">
                                    <div class="skeleton-row"><div class="skeleton-block skeleton-avatar"></div><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-md"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-row"><div class="skeleton-block skeleton-avatar"></div><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-md"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-row"><div class="skeleton-block skeleton-avatar"></div><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-md"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-row"><div class="skeleton-block skeleton-avatar"></div><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-md"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-row"><div class="skeleton-block skeleton-avatar"></div><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-md"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>System Roles</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter roles..." onkeyup="filterTable(this, 'container-wst_Roles')">
                                    </div>
                                    <button class="btn-add-new" onclick="showMasterModal('wst_Roles', 'RoleName')">
                                        <ion-icon name="add-outline"></ion-icon> Add
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-wst_Roles" data-lazy-table="wst_Roles">
                                <div class="table-content-wrapper">
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Master Tab -->
        <div id="tab-products" class="settings-tab-content d-none h-100">
            <div class="row g-4">
                <div class="col-md-6 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>Categories</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter categories..." onkeyup="filterTable(this, 'container-wst_PCategories')">
                                    </div>
                                    <button class="btn-add-new" onclick="showMasterModal('wst_PCategories', 'CategoryName')">
                                        <ion-icon name="add-outline"></ion-icon> Add
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-wst_PCategories" data-lazy-table="wst_PCategories">
                                <div class="table-content-wrapper">
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>Descriptions</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter descriptions..." onkeyup="filterTable(this, 'container-wst_PDescriptions')">
                                    </div>
                                    <button class="btn-add-new" onclick="showMasterModal('wst_PDescriptions', 'DescriptionName')">
                                        <ion-icon name="add-outline"></ion-icon> Add
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-wst_PDescriptions" data-lazy-table="wst_PDescriptions">
                                <div class="table-content-wrapper">
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Config Tab -->
        <div id="tab-logs" class="settings-tab-content d-none h-100">
            <div class="row g-4">
                <div class="col-md-6 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>Log Types</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter types..." onkeyup="filterTable(this, 'container-wst_LogTypes')">
                                    </div>
                                    <button class="btn-add-new" onclick="showMasterModal('wst_LogTypes', 'TypeName')">
                                        <ion-icon name="add-outline"></ion-icon> Add
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-wst_LogTypes" data-lazy-table="wst_LogTypes">
                                <div class="table-content-wrapper">
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>Shifts</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter shifts..." onkeyup="filterTable(this, 'container-wst_Shifts')">
                                    </div>
                                    <button class="btn-add-new" onclick="showMasterModal('wst_Shifts', 'ShiftName')">
                                        <ion-icon name="add-outline"></ion-icon> Add
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-wst_Shifts" data-lazy-table="wst_Shifts">
                                <div class="table-content-wrapper">
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Organizational Tab -->
        <div id="tab-org" class="settings-tab-content d-none h-100">
            <div class="row g-4">
                <div class="col-md-6 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>Phases</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter phases..." onkeyup="filterTable(this, 'container-wst_Phases')">
                                    </div>
                                    <button class="btn-add-new" onclick="showMasterModal('wst_Phases', 'PhaseName')">
                                        <ion-icon name="add-outline"></ion-icon> Add
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-wst_Phases" data-lazy-table="wst_Phases">
                                <div class="table-content-wrapper">
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 d-flex flex-column">
                    <div class="settings-card shadow-sm">
                        <div class="settings-card-body">
                            <div class="settings-card-header">
                                <h5>Production Areas</h5>
                                <div class="settings-toolbar">
                                    <div class="settings-search">
                                        <ion-icon name="search-outline" class="search-icon"></ion-icon>
                                        <input type="text" placeholder="Filter areas..." onkeyup="filterTable(this, 'container-wst_Areas')">
                                    </div>
                                    <button class="btn-add-new" onclick="showMasterModal('wst_Areas', 'AreaName')">
                                        <ion-icon name="add-outline"></ion-icon> Add
                                    </button>
                                </div>
                            </div>
                            <div class="scrollable-container" id="container-wst_Areas" data-lazy-table="wst_Areas">
                                <div class="table-content-wrapper">
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                    <div class="skeleton-list-item"><div class="skeleton-block skeleton-text-lg"></div><div class="skeleton-block skeleton-text-sm"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Personnel Modal -->
<div class="modal fade" id="personnelModal" tabindex="-1" aria-labelledby="personnelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="personnelModalTitle">Assign Personnel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="personnelForm">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="userId" id="formUserId">

                    <div class="form-group mb-3 position-relative">
                        <label class="form-label text-muted small fw-bold">Username / ID</label>
                        <input type="text" class="form-control premium-input" name="username" id="formUsername" placeholder="Search by name or ID..." required autocomplete="off">
                        <div id="searchSuggestions" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1000; top: 100%;"></div>
                        <small class="text-muted" style="font-size:0.75rem;">Must match LRNPH system username / biometrics ID</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label text-muted small fw-bold">App Role</label>
                        <select class="form-select premium-input" name="roleId" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['RoleID'] ?>"><?= htmlspecialchars($r['RoleName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">Phase</label>
                            <select class="form-select premium-input" name="phaseId">
                                <option value="">None / All</option>
                                <?php foreach ($phases as $p): ?>
                                    <option value="<?= $p['PhaseID'] ?>"><?= htmlspecialchars($p['PhaseName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold">Area</label>
                            <select class="form-select premium-input" name="areaId">
                                <option value="">None / All</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= $a['AreaID'] ?>"><?= htmlspecialchars($a['AreaName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" onclick="document.getElementById('personnelForm').requestSubmit()">Save Configuration</button>
            </div>
        </div>
    </div>
</div>

<!-- Master Data Modal -->
<div class="modal fade" id="masterModal" tabindex="-1" aria-labelledby="masterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="masterModalTitle">Add Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="masterForm">
                    <input type="hidden" name="action" value="save_master">
                    <input type="hidden" name="table" id="masterTable">
                    <input type="hidden" name="column" id="masterColumn">
                    <input type="hidden" name="idVal" id="masterIdVal">
                    <input type="hidden" name="idCol" id="masterIdCol">

                    <div class="form-group mb-3">
                        <label class="form-label text-muted small fw-bold" id="masterLabel">Entry Name</label>
                        <input type="text" class="form-control premium-input" name="value" id="masterValue" required autocomplete="off">
                    </div>

                    <div class="form-group mb-3 d-none" id="masterPhaseGroup">
                        <label class="form-label text-muted small fw-bold">Assigned Phase</label>
                        <select class="form-select premium-input" name="phaseId" id="masterPhaseId">
                            <option value="">None / Global</option>
                            <?php foreach ($phases as $p): ?>
                                <option value="<?= $p['PhaseID'] ?>"><?= htmlspecialchars($p['PhaseName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" onclick="document.getElementById('masterForm').requestSubmit()">Add Item</button>
            </div>
        </div>
    </div>
</div>

<!-- Special Permissions Modal -->
<div class="modal fade" id="permissionsModal" tabindex="-1" aria-labelledby="permissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Manage Role Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="permissionsForm">
                    <input type="hidden" name="roleId" id="permRoleId">
                    
                    <div class="form-group mb-4">
                        <label class="form-label text-muted small fw-bold">Role Name</label>
                        <input type="text" name="roleName" id="permRoleNameInput" class="form-control premium-input" placeholder="Role Name" required>
                    </div>

                    <p class="text-muted small mb-2 fw-bold">Capabilities</p>
                    <p class="text-muted small mb-3">Select the capabilities assigned to this role.</p>
                    
                    <div id="permissionsList" class="permissions-grid">
                        <!-- Loaded dynamically -->
                        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" onclick="savePermissions()">Save Permissions</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../components/scripts.php'; ?>
<script>
// ============================================================
// Dynamic Pagination State
// ============================================================
const tableState = {};

// ============================================================
// TAB SWITCHING with Sliding Indicator
// ============================================================
function updateIndicator(btn) {
    const nav = document.getElementById('tabNav');
    const indicator = document.getElementById('tabIndicator');
    if (!btn || !indicator || !nav) return;

    const navRect = nav.getBoundingClientRect();
    const btnRect = btn.getBoundingClientRect();

    indicator.style.width = btnRect.width + 'px';
    indicator.style.transform = `translateX(${btnRect.left - navRect.left - 5}px)`;
}

function switchTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.settings-tab-content').forEach(el => el.classList.add('d-none'));
    const allBtns = document.querySelectorAll('.settings-tab-btn');
    allBtns.forEach(el => el.classList.remove('active'));

    const targetTab = document.getElementById('tab-' + tabId);
    if (targetTab) {
        targetTab.classList.remove('d-none');
        // Re-trigger animation
        targetTab.style.animation = 'none';
        targetTab.offsetHeight; // Force reflow
        targetTab.style.animation = '';

        // Lazy load tables if needed
        targetTab.querySelectorAll('[data-lazy-table]').forEach(container => {
            if (!container.dataset.loaded) {
                fetchTableData(container.dataset.lazyTable, container.id);
            }
        });
    }

    // Activate button & move indicator
    const activeBtn = Array.from(allBtns).find(btn => btn.dataset.tab === tabId);
    if (activeBtn) {
        activeBtn.classList.add('active');
        updateIndicator(activeBtn);
    }
}

// ============================================================
// DATA FETCHING
// ============================================================
function fetchTableData(table, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    fetch(`../api/get_settings_data.php?table=${table}`)
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            container.innerHTML = `<div class="empty-state"><ion-icon name="alert-circle-outline"></ion-icon><p>${res.message}</p></div>`;
            return;
        }

        tableState[containerId] = {
            allData: res.data,
            table: table,
            config: res.config,
            currentPage: 1,
            pageSize: 6,
            filter: ''
        };

        renderSettingsTable(containerId);
        container.dataset.loaded = "true";
    })
    .catch(err => {
        container.innerHTML = `<div class="empty-state"><ion-icon name="cloud-offline-outline"></ion-icon><p>Failed to load data</p></div>`;
    });
}

// ============================================================
// TABLE RENDERING — Card List Design
// ============================================================
function renderSettingsTable(containerId) {
    const state = tableState[containerId];
    if (!state) return;

    const container = document.getElementById(containerId);
    if (!container) return;

    const { allData, table, config, currentPage, pageSize, filter } = state;

    // Filter
    const filteredData = allData.filter(item => {
        if (!filter) return true;
        return JSON.stringify(item).toLowerCase().includes(filter.toLowerCase());
    });

    // Paginate
    const totalItems = filteredData.length;
    const totalPages = Math.ceil(totalItems / pageSize);
    const start = (currentPage - 1) * pageSize;
    const paginatedData = filteredData.slice(start, start + pageSize);

    // Get or create wrappers
    let contentWrapper = container.querySelector('.table-content-wrapper');
    let paginationWrapper = container.querySelector('.table-pagination-wrapper');

    if (!paginationWrapper) {
        // First render — clear skeleton, create structure
        container.innerHTML = '';

        contentWrapper = document.createElement('div');
        contentWrapper.className = 'table-content-wrapper flex-grow-1';
        container.appendChild(contentWrapper);

        paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'table-pagination-wrapper mt-auto';
        container.appendChild(paginationWrapper);
    }

    // Build content
    let html = '';
    if (paginatedData.length === 0) {
        html = `<div class="empty-state">
            <ion-icon name="search-outline"></ion-icon>
            <p>No records found${filter ? ' matching "' + filter + '"' : ''}</p>
        </div>`;
    } else if (table === 'wst_Users') {
        html = paginatedData.map(user => {
            const displayName = user.FullName && user.FullName.trim() ? user.FullName : user.Username;
            const initials = displayName.substring(0, 2).toUpperCase();
            return `
            <div class="data-row">
                <div class="row-avatar">${initials}</div>
                <div class="row-info">
                    <span class="row-primary">${displayName}</span>
                    <span class="row-badge">${user.RoleName || 'N/A'}</span>
                    <div class="row-meta">
                        <span class="row-secondary">ID: ${user.Username}</span>
                        <span class="row-secondary">·</span>
                        <span class="row-secondary">${user.PhaseName || '—'}</span>
                        <span class="row-secondary">·</span>
                        <span class="row-secondary">${user.AreaName || '—'}</span>
                    </div>
                </div>
                <div class="row-actions">
                    <button class="btn-row-action btn-row-edit" onclick='editUser(${JSON.stringify(user)})' title="Edit">
                        <ion-icon name="create-outline"></ion-icon>
                    </button>
                    <button class="btn-row-action btn-row-delete" onclick="deleteRecord('wst_Users', 'UserID', ${user.UserID})" title="Delete">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </div>`;
        }).join('');
    } else if (table === 'wst_Roles') {
        html = paginatedData.map(role => `
            <div class="list-item">
                <span class="list-item-name">${role.RoleName}</span>
                <div class="row-actions">
                    <button class="btn-row-action btn-row-edit" onclick="showPermissionsModal(${role.RoleID}, '${role.RoleName}')" title="Manage Role & Permissions">
                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                    </button>
                    <button class="btn-row-action btn-row-delete" onclick="deleteRecord('wst_Roles', 'RoleID', ${role.RoleID})" title="Delete">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </div>
        `).join('');
    } else {
        html = paginatedData.map(item => `
            <div class="list-item">
                <div class="row-info">
                    <span class="list-item-name">${item[config.name]}</span>
                    ${table === 'wst_LogTypes' ? `<span class="row-badge ms-2" style="font-size:0.6rem;">${item.PhaseName || 'Global'}</span>` : ''}
                </div>
                <div class="row-actions">
                    <button class="btn-row-action btn-row-edit" onclick='editMaster(${JSON.stringify(item)}, "${table}", "${config.id}", "${config.name}")' title="Edit">
                        <ion-icon name="create-outline"></ion-icon>
                    </button>
                    <button class="btn-row-action btn-row-delete" onclick="deleteRecord('${table}', '${config.id}', ${item[config.id]})" title="Delete">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Pagination
    let paginationHtml = '';
    if (totalPages > 1) {
        paginationHtml = `
            <div class="premium-pagination">
                <span class="pagination-info">Showing ${start + 1}–${Math.min(start + pageSize, totalItems)} of ${totalItems}</span>
                <div class="pagination-capsule">
                    <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage('${containerId}', ${currentPage - 1})">
                        <ion-icon name="chevron-back-outline"></ion-icon>
                    </button>
                    <span class="page-current">${currentPage} / ${totalPages}</span>
                    <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage('${containerId}', ${currentPage + 1})">
                        <ion-icon name="chevron-forward-outline"></ion-icon>
                    </button>
                </div>
            </div>`;
    }

    contentWrapper.innerHTML = html;
    paginationWrapper.innerHTML = paginationHtml;
}

function changePage(containerId, newPage) {
    if (tableState[containerId]) {
        tableState[containerId].currentPage = newPage;
        renderSettingsTable(containerId);
    }
}

function filterTable(input, containerId) {
    if (tableState[containerId]) {
        tableState[containerId].filter = input.value;
        tableState[containerId].currentPage = 1;
        renderSettingsTable(containerId);
    }
}

// ============================================================
// MODALS — Personnel
// ============================================================
let personnelModalInstance = null;

function showAddUserModal() {
    if(!personnelModalInstance) personnelModalInstance = new bootstrap.Modal(document.getElementById('personnelModal'));
    document.getElementById('personnelForm').reset();
    document.getElementById('formUserId').value = '';
    document.getElementById('personnelModalTitle').textContent = 'Assign Personnel';
    personnelModalInstance.show();
}

function editUser(user) {
    if(!personnelModalInstance) personnelModalInstance = new bootstrap.Modal(document.getElementById('personnelModal'));
    document.getElementById('formUserId').value = user.UserID;
    document.getElementById('formUsername').value = user.Username;
    document.querySelector('#personnelModal select[name="roleId"]').value = user.RoleID;
    document.querySelector('#personnelModal select[name="phaseId"]').value = user.PhaseID || '';
    document.querySelector('#personnelModal select[name="areaId"]').value = user.AreaID || '';
    document.getElementById('personnelModalTitle').textContent = 'Edit Assignment';
    personnelModalInstance.show();
}

// ============================================================
// MODALS — Master Data
// ============================================================
let masterModalInstance = null;

function showMasterModal(table, column) {
    if(!masterModalInstance) masterModalInstance = new bootstrap.Modal(document.getElementById('masterModal'));
    document.getElementById('masterForm').reset();
    document.getElementById('masterTable').value = table;
    document.getElementById('masterColumn').value = column;
    document.getElementById('masterIdVal').value = '';
    document.getElementById('masterIdCol').value = '';
    
    // Friendly label based on table
    let label = "Entry Name";
    if (table.includes('Role')) label = "Role Name";
    else if (table.includes('Phase')) label = "Phase Name";
    else if (table.includes('Area')) label = "Area Name";
    else if (table.includes('Shift')) label = "Shift Name";
    
    document.getElementById('masterLabel').textContent = label;
    document.getElementById('masterModalTitle').textContent = "Add New " + label.replace(' Name', '');

    // Show Phase select if managing Log Types
    const phaseGroup = document.getElementById('masterPhaseGroup');
    if (table === 'wst_LogTypes') {
        phaseGroup.classList.remove('d-none');
    } else {
        phaseGroup.classList.add('d-none');
    }

    masterModalInstance.show();
}

function editMaster(item, table, idCol, nameCol) {
    if(!masterModalInstance) masterModalInstance = new bootstrap.Modal(document.getElementById('masterModal'));
    document.getElementById('masterForm').reset();
    document.getElementById('masterTable').value = table;
    document.getElementById('masterColumn').value = nameCol;
    document.getElementById('masterIdVal').value = item[idCol];
    document.getElementById('masterIdCol').value = idCol;
    document.getElementById('masterValue').value = item[nameCol];
    
    // Friendly label based on table
    let label = "Entry Name";
    if (table.includes('Role')) label = "Role Name";
    else if (table.includes('Phase')) label = "Phase Name";
    else if (table.includes('Area')) label = "Area Name";
    else if (table.includes('Shift')) label = "Shift Name";
    
    document.getElementById('masterLabel').textContent = label;
    document.getElementById('masterModalTitle').textContent = "Edit " + label.replace(' Name', '');

    // Show Phase select if managing Log Types
    const phaseGroup = document.getElementById('masterPhaseGroup');
    if (table === 'wst_LogTypes') {
        phaseGroup.classList.remove('d-none');
        document.getElementById('masterPhaseId').value = item.PhaseID || '';
    } else {
        phaseGroup.classList.add('d-none');
    }

    masterModalInstance.show();
}

// ============================================================
// PERMISSIONS MANAGEMENT
// ============================================================
let permissionsModalInstance = null;

function showPermissionsModal(roleId, roleName) {
    if(!permissionsModalInstance) permissionsModalInstance = new bootstrap.Modal(document.getElementById('permissionsModal'));
    
    document.getElementById('permRoleId').value = roleId;
    document.getElementById('permRoleNameInput').value = roleName;
    const listContainer = document.getElementById('permissionsList');
    listContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted"></div></div>';
    
    permissionsModalInstance.show();

    fetch(`../api/get_permissions.php?roleId=${roleId}`)
    .then(r => r.json())
    .then(res => {
        if (!res.success) throw new Error(res.error);
        
        // Group by category
        const groups = {};
        res.permissions.forEach(p => {
            if (!groups[p.Category]) groups[p.Category] = [];
            groups[p.Category].push(p);
        });

        let html = '';
        for (const cat in groups) {
            html += `<div class="permission-group mb-3">
                <h6 class="permission-category-title">${cat}</h6>
                <div class="permission-items">`;
            groups[cat].forEach(p => {
                const checked = p.assigned ? 'checked' : '';
                html += `
                <div class="permission-item">
                    <label class="permission-label-check">
                        <input type="checkbox" name="permissions[]" value="${p.PermissionID}" ${checked}>
                        <div class="permission-details">
                            <span class="p-name">${p.PermissionLabel}</span>
                            <span class="p-key">${p.PermissionKey}</span>
                        </div>
                    </label>
                </div>`;
            });
            html += `</div></div>`;
        }
        listContainer.innerHTML = html;
    })
    .catch(err => {
        listContainer.innerHTML = `<div class="alert alert-danger small">${err.message}</div>`;
    });
}

function savePermissions() {
    const form = document.getElementById('permissionsForm');
    const formData = new FormData(form);

    fetch('../api/manage_permissions.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            Swal.fire({
                title: 'Authorized!',
                text: 'Role settings updated successfully.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

// ============================================================
// SEARCH SUGGESTIONS (Employee lookup)
// ============================================================
const searchInput = document.getElementById('formUsername');
const suggestions = document.getElementById('searchSuggestions');

searchInput.oninput = function() {
    const q = this.value;
    if (q.length < 2) {
        suggestions.classList.add('d-none');
        return;
    }

    fetch('../api/search_employees.php?q=' + q)
    .then(r => r.json())
    .then(data => {
        if (data.length > 0) {
            suggestions.innerHTML = data.map(emp => `
                <button type="button" class="list-group-item list-group-item-action py-2" onclick="selectEmployee('${emp.username}')">
                    <div class="fw-bold">${emp.full_name}</div>
                    <div class="small text-muted">${emp.username} - ${emp.PositionTitle}</div>
                </button>
            `).join('');
            suggestions.classList.remove('d-none');
        } else {
            suggestions.classList.add('d-none');
        }
    });
};

function selectEmployee(username) {
    searchInput.value = username;
    suggestions.classList.add('d-none');
}

// ============================================================
// FORM SUBMISSION
// ============================================================
document.getElementById('personnelForm').onsubmit = handleFormSubmit;
document.getElementById('masterForm').onsubmit = handleFormSubmit;

function handleFormSubmit(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('../api/manage_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            Swal.fire('Saved!', res.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    });
}

function deleteRecord(table, idCol, idVal) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This could break linked records if not handled by DB constraints!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#202227'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('table', table);
            formData.append('idCol', idCol);
            formData.append('idVal', idVal);

            fetch('../api/manage_settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.success) location.reload();
                else Swal.fire('Error', res.error, 'error');
            });
        }
    });
}

// ============================================================
// ESC KEY handled natively by Bootstrap Modals
// ============================================================

// ============================================================
// INITIALIZATION
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    switchTab('personnel');

    // Initialize indicator after ionicons load (slight delay for icon rendering)
    setTimeout(() => {
        const activeBtn = document.querySelector('.settings-tab-btn.active');
        if (activeBtn) updateIndicator(activeBtn);
    }, 100);

    // Update indicator on window resize
    window.addEventListener('resize', () => {
        const activeBtn = document.querySelector('.settings-tab-btn.active');
        if (activeBtn) updateIndicator(activeBtn);
    });
});
</script>
</body>
</html>
