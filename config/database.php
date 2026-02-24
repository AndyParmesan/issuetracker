<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'issuetracker');
define('DB_USER', 'root'); // Change 'aj' to 'root'
define('DB_PASS', '');     // Change 'root' to an empty string ''
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);

$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    // This creates a global $pdo variable available to any file that requires this
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]));
}