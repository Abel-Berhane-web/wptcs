<?php
/**
 * Database Connection Configuration Template
 * Copy this file to database.php and add your actual credentials.
 */

// Environment Detection (Local WAMP vs Production)
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);

if ($is_local) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'wptcs_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'your_production_host');
    define('DB_NAME', 'your_production_db');
    define('DB_USER', 'your_production_user');
    define('DB_PASS', 'your_production_password');
}

define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection instance (singleton pattern)
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please contact the administrator.");
        }
    }

    return $pdo;
}
