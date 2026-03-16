<?php
/**
 * Database Connection - PostgreSQL (Neon)
 */
date_default_timezone_set('Asia/Manila');

// Priority 1: DATABASE_URL (Standard for Neon-Vercel integration)
if ($db_url = getenv('DATABASE_URL')) {
    $db_parts = parse_url($db_url);
    $host     = $db_parts['host'];
    $port     = $db_parts['port'] ?? "5432";
    $database = ltrim($db_parts['path'], '/');
    $user     = $db_parts['user'];
    $password = $db_parts['pass'];
} 
// Priority 2: Individual Environment Variables
elseif (getenv('DB_HOST')) {
    $host     = getenv('DB_HOST');
    $port     = getenv('DB_PORT')     ?: "5432";
    $database = getenv('DB_DATABASE');
    $user     = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
} 
// Priority 3: Fail
else {
    error_log("Database Configuration Missing: Set DATABASE_URL or DB_HOST.");
    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">System Configuration Error</h2>
            <p>The database connection string is missing. Please ensure <code>DATABASE_URL</code> is set in Vercel.</p>
         </div>");
}

try {
    $sslmode = getenv('DB_SSLMODE') ?: "require";
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=$sslmode";
    $conn = new PDO($dsn, $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['error_msg'] = "Database connection failed.";
    
    die("<div style=\"font-family:sans-serif; padding:50px; text-align:center;\">
            <h2 style=\"color:#e11d48;\">System Unavailable</h2>
            <p>Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>
            <p style=\"color: #666; font-size: 0.9em;\">If you are on XAMPP, ensure <code>extension=pdo_pgsql</code> is enabled in your php.ini.</p>
            <button onclick=\"location.reload()\" style=\"padding:10px 20px; background:#181a1f; color:#fff; border:none; border-radius:5px; cursor:pointer;\">Retry</button>
         </div>");
}
?>
