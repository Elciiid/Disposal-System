<?php
/**
 * test_connection.php
 * Simple script to verify the connection to Neon PostgreSQL.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Disposal System: Database Connection Test</h2>";

echo "<h3>Debug Info:</h3>";
echo "<ul>";
echo "<li><strong>Current Dir (__DIR__):</strong> " . __DIR__ . "</li>";
echo "<li><strong>Working Dir (getcwd):</strong> " . getcwd() . "</li>";
echo "<li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "</ul>";

echo "<h4>Directory Listing (Root):</h4><pre>";
$files = scandir(__DIR__);
foreach($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $type = is_dir(__DIR__ . '/' . $file) ? '[DIR]' : '[FILE]';
    echo "$type $file\n";
}
echo "</pre>";

$connDir = __DIR__ . '/connection';
if (is_dir($connDir)) {
    echo "<h4>Directory Listing ($connDir):</h4><pre>";
    $conn_files = scandir($connDir);
    if ($conn_files !== false) {
        foreach($conn_files as $file) {
            if ($file === '.' || $file === '..') continue;
            $type = is_dir($connDir . '/' . $file) ? '[DIR]' : '[FILE]';
            echo "$type $file\n";
        }
    } else {
        echo "Could not scandir $connDir";
    }
    echo "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ connection/ directory NOT found at $connDir</p>";
}

$connectionFile = __DIR__ . '/connection/database.php';

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

    // 3. Check for mock users and verify password hash
    $userCount = $conn->query("SELECT COUNT(*) FROM wst_Users")->fetchColumn();
    echo "<p><strong>Mock Users Found:</strong> $userCount</p>";

    if ($userCount > 0) {
        $stmt = $conn->prepare("SELECT password FROM wst_Users WHERE username = '3096'");
        $stmt->execute();
        $hash = $stmt->fetchColumn();
        
        echo "<h3>Password Verification Check (User 3096):</h3>";
        if ($hash) {
            $isValid = ($password_to_check === $hash);
            if ($isValid) {
                echo "<p style='color: green;'>✅ Password '$password_to_check' is VALID for user 3096 (Plain Text).</p>";
            } else {
                echo "<p style='color: red;'>❌ Password '$password_to_check' is INVALID for user 3096.</p>";
                echo "<p>Stored Hash: <code>$hash</code></p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ User 3096 not found for password check.</p>";
        }
    }

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
