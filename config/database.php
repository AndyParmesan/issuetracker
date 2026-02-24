<?php
// ============================================================
//  configs/database.php  —  IssueTracker DB Connection (PDO)
// ============================================================

define('DB_HOST', '127.0.0.1');  // Use IP to avoid socket issues in XAMPP
define('DB_NAME', 'issuetracker');
define('DB_USER', 'aj');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
