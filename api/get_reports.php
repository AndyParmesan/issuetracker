<?php
require_once '../config/database.php';

try {
    $issueId = $_GET['id'] ?? null;
    if (!$issueId) {
        throw new Exception("Issue ID is required.");
    }

    // 1. Fetch the main issue details
    $stmt = $pdo->prepare("SELECT * FROM issues WHERE id = ?");
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch();

    if (!$issue) {
        throw new Exception("Issue not found.");
    }

    // 2. Fetch all comments for this issue
    $commentStmt = $pdo->prepare("SELECT author, comment, created_at FROM comments WHERE issue_id = ? ORDER BY created_at ASC");
    $commentStmt->execute([$issueId]);
    $comments = $commentStmt->fetchAll();

    // 3. Combine the data for the report
    $reportData = [
        "issue" => $issue,
        "comments" => $comments
    ];

    echo json_encode(["data" => $reportData]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => $e->getMessage()]);
}
?>