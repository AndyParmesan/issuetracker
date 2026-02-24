<?php
require_once '../config/database.php';

$issueId = $_GET['issueId'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET' && $issueId) {
        // Conversion of GetCommentsAsync
        $stmt = $pdo->prepare("SELECT c.*, u.name AS user_name FROM comments c 
                               LEFT JOIN users u ON c.user_id = u.id 
                               WHERE c.issue_id = :issueId ORDER BY c.created_at ASC");
        $stmt->execute([':issueId' => $issueId]);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);

    } elseif ($method === 'POST' && $issueId) {
        // Conversion of AddCommentAsync
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO comments (issue_id, user_id, comment) VALUES (:issueId, :userId, :comment)");
        $stmt->execute([
            ':issueId' => $issueId,
            ':userId'  => $data['userId'],
            ':comment' => $data['commentText']
        ]);
        echo json_encode(["success" => true, "message" => "Comment added."]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>