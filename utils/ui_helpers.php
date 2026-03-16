<?php
/**
 * Renders a standard master data table with delete action
 * 
 * @param array $data The result set from fetchAllFromTable
 * @param string $tableName The table name (for delete logic)
 * @param string $idColumn The primary key column name
 * @param string $nameColumn The display name column
 */
function renderMasterDataTable($data, $tableName, $idColumn, $nameColumn) {
    if (empty($data)) {
        echo '<div class="text-center py-4 text-muted"><small>No entries found.</small></div>';
        return;
    }
    ?>
    <div class="scrollable-container">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-transparent">
                <tr>
                    <th class="ps-3">Name</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr>
                    <td class="ps-3 fw-medium"><?= htmlspecialchars($row[$nameColumn]) ?></td>
                    <td class="text-end pe-3">
                        <button class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="deleteRecord('<?= $tableName ?>', '<?= $idColumn ?>', <?= $row[$idColumn] ?>)">
                            <ion-icon name="trash-outline" style="font-size: 1.2rem;"></ion-icon>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
