<?php
require_once '../config/database.php';

// Accept id from ?id=X (works with GET or DELETE method)
$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(["success" => false, "message" => "No ID provided. Use ?id=X"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get attachment filenames so we can delete physical files
    $attStmt = $pdo->prepare("SELECT filename FROM attachments WHERE issue_id = :id");
    $attStmt->execute([':id' => $id]);
    $attachments = $attStmt->fetchAll();

    foreach ($attachments as $att) {
        $filePath = '../uploads/' . $att['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // 2. Delete associated comments
    $stmt = $pdo->prepare("DELETE FROM comments WHERE issue_id = :id");
    $stmt->execute([':id' => $id]);

    // 3. Delete associated attachments records
    $stmt = $pdo->prepare("DELETE FROM attachments WHERE issue_id = :id");
    $stmt->execute([':id' => $id]);

    // 4. Delete the issue
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
