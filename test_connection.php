<?php
/**
 * test_connection.php
 * Simple script to verify the connection to Neon PostgreSQL.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Disposal System: Database Connection Test</h2>";

$connectionFile = __DIR__ . '/connection/database.php';
if (!file_exists($connectionFile)) {
    // Fallback for different environments
    $connectionFile = 'connection/database.php';
}

if (!file_exists($connectionFile)) {
    echo "<p style='color: red;'>❌ Error: Connection file not found at: $connectionFile</p>";
    exit();
}

try {
    // Include the database connection
    require_once $connectionFile;

    // 1. Test basic connection and get Postgres version
    $stmt = $conn->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "<p style='color: green;'>✅ Successfully connected to database!</p>";
    echo "<p><strong>Server Version:</strong> $version</p>";

    // 2. Test if core tables exist
    echo "<h3>Table Check:</h3><ul>";
    $tables = ['wst_Users', 'wst_Logs', 'wst_Phases', 'wst_Roles'];
    foreach ($tables as $table) {
        $check = $conn->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)");
        $check->execute([strtolower($table)]);
        $exists = $check->fetchColumn();
        
        if ($exists) {
            echo "<li>✅ Table <strong>$table</strong> exists.</li>";
        } else {
            echo "<li style='color: orange;'>⚠️ Table <strong>$table</strong> NOT found. (Make sure you ran the schema script)</li>";
        }
    }
    echo "</ul>";

    // 3. Check for mock users
    $userCount = $conn->query("SELECT COUNT(*) FROM wst_Users")->fetchColumn();
    echo "<p><strong>Mock Users Found:</strong> $userCount</p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Connection Failed!</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    
    echo "<p><strong>Troubleshooting Tips:</strong></p>";
    echo "<ul>";
    echo "<li>Check your Environment Variables (DB_HOST, DB_USER, etc.).</li>";
    echo "<li>If running locally, ensure you have the <code>php_pdo_pgsql</code> extension enabled in your php.ini.</li>";
    echo "<li>Ensure the database honors connections from your current IP.</li>";
    echo "</ul>";
}
?>
