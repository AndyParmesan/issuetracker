<?php
require_once '../config/database.php';

try {
    // Fetch report history from the database
    $stmt = $pdo->query("SELECT * FROM reports ORDER BY generated_at DESC");
    $reports = $stmt->fetchAll();

    echo json_encode(["data" => $reports]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => $e->getMessage()]);
}
?>