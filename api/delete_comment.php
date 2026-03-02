<?php
require_once '../config/database.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Comment ID is required."]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "Comment not found."]);
    } else {
        echo json_encode(["success" => true, "message" => "Comment deleted."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]);
}
?>
