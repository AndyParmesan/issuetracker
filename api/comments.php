<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // FETCH COMMENTS FOR THE MODAL
        $issueId = $_GET['issueId'] ?? null;
        if (!$issueId) throw new Exception("Issue ID required");

        $stmt = $pdo->prepare("SELECT author, comment, created_at FROM comments WHERE issue_id = ? ORDER BY created_at ASC");
        $stmt->execute([$issueId]);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);

    } elseif ($method === 'POST') {
        // SAVE A NEW COMMENT
        $data = json_decode(file_get_contents('php://input'), true);
        $issueId = $data['issueId'] ?? null;
        $author  = $data['author'] ?? 'Admin User';
        $comment = $data['comment'] ?? '';

        if (!$issueId || empty($comment)) throw new Exception("Missing data");

        $stmt = $pdo->prepare("INSERT INTO comments (issue_id, author, comment, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$issueId, $author, $comment]);
        echo json_encode(["success" => true]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>