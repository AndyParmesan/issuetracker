<?php
require_once '../config/database.php'; // Your connection file

try {
    // direct conversion of UserService.GetAllAsync()
    $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY name");
    $users = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $users
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>