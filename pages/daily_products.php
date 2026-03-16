<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../auth/auth_helpers.php';

// Check permission
if (!hasPermission($conn, 'view_daily_products')) {
    $_SESSION['error_msg'] = "Access Denied: You do not have permission to view this page.";
    header("Location: dashboard.php");
    exit();
}

$currentUser = getCurrentUser();
$pageTitle = 'Daily Products - Waste Logs';
$extraCSS = ['supervisor.css'];

// Fetch today's products, aggregated by Product and Log Type
$sql = "SELECT 
            c.CategoryName as Product,
            t.TypeName as LogType,
            SUM(w.KG) as TotalKG,
            a.AreaName as Area
        FROM wst_Logs w
        LEFT JOIN wst_PCategories c ON w.CategoryID = c.CategoryID
        LEFT JOIN wst_LogTypes t ON w.TypeID = t.TypeID
        LEFT JOIN wst_Areas a ON w.AreaID = a.AreaID
        WHERE CAST(w.LogDate AS DATE) = CAST(GETDATE() AS DATE)
        GROUP BY c.CategoryName, t.TypeName, a.AreaName
        ORDER BY c.CategoryName ASC";

try {
    $stmt = $conn->query($sql);
    $dailyProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dailyProducts = [];
    $error = "Failed to load data: " . $e->getMessage();
}

require_once '../components/header.php';
?>
<body>
<div class="dashboard-wrapper">
    <?php 
    require_once '../api/approval_workflow.php';
    include '../components/sidebar.php'; 
    ?>
    <main class="main-content">
        <?php include '../components/topbar.php'; ?>
        
        <div class="pe-2 mt-2" style="flex-grow: 1; height: calc(100vh - 80px); display: flex; flex-direction: column;">
            <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
                <div>
                    <h1 class="page-title" style="letter-spacing: -1px;">Today's Products</h1>
                    <p class="page-subtitle text-muted mt-1">Aggregated list of products submitted today.</p>
                </div>
                <div class="d-flex align-items-center gap-3 pb-2">
                    <span class="text-muted fw-medium d-none d-md-inline" style="font-size: 0.95rem;"><?= date('d F, Y') ?></span>
                </div>
            </div>

            <div class="data-card bg-white shadow-sm border-0" style="flex-grow: 1; overflow: hidden; display: flex; flex-direction: column; border-radius: 16px;">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger m-3 border-0 rounded-3"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div class="table-responsive" style="flex-grow: 1; overflow-y: auto;">
                    <table class="table table-hover align-middle custom-table mb-0">
                        <thead class="sticky-top bg-white z-1" style="box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                            <tr>
                                <th class="text-uppercase text-muted border-0 py-3 ps-4" style="font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 700; width: 30%;">Product</th>
                                <th class="text-uppercase text-muted border-0 py-3" style="font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 700; width: 25%;">Area</th>
                                <th class="text-uppercase text-muted border-0 py-3" style="font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 700; width: 25%;">Log Type</th>
                                <th class="text-uppercase text-muted text-end border-0 py-3 pe-4" style="font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 700; width: 20%;">Total KG</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dailyProducts)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5 border-0">
                                        <ion-icon name="document-text-outline" style="font-size: 3rem; color: #cbd5e1;"></ion-icon>
                                        <p class="mt-2 text-muted fw-medium">No products logged today.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dailyProducts as $row): ?>
                                    <tr>
                                        <td class="fw-bold text-dark border-bottom ps-4" style="border-color: #f1f5f9;"><?= htmlspecialchars($row['Product'] ?? 'Unknown Item') ?></td>
                                        <td class="border-bottom" style="border-color: #f1f5f9;">
                                            <span class="text-muted fw-bold" style="font-size: 0.85rem;">
                                                <ion-icon name="location-outline" class="me-1"></ion-icon><?= htmlspecialchars($row['Area'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="border-bottom" style="border-color: #f1f5f9;">
                                            <span class="badge rounded-pill fw-medium" style="background-color: var(--accent-yellow); color: #181a1f; padding: 0.4em 0.8em; font-size: 0.75rem;">
                                                <?= htmlspecialchars($row['LogType'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold border-bottom text-dark pe-4" style="border-color: #f1f5f9;"><?= number_format($row['TotalKG'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once '../components/scripts.php'; ?>
</body>
</html>
