<?php
require_once '../config/database.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(["success" => false, "message" => "No ID provided."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Delete associated comments first
    $stmt = $pdo->prepare("DELETE FROM comments WHERE issue_id = :id");
    $stmt->execute([':id' => $id]);

    // 2. Delete associated attachment records
    $stmt = $pdo->prepare("DELETE FROM attachments WHERE issue_id = :id");
    $stmt->execute([':id' => $id]);

    // 3. Delete the issue
    $stmt = $pdo->prepare("DELETE FROM issues WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Issue deleted successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>