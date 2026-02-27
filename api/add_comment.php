<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../config/database.php';

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!$data) {
    die(json_encode(["success" => false, "message" => "Invalid JSON input."]));
}

$issueId = $data['issueId'] ?? null;
$comment = trim($data['comment'] ?? '');
$userId  = $data['userId']  ?? null;    // numeric user ID (preferred)
$author  = $data['author']  ?? null;    // fallback display name

if (!$issueId || empty($comment)) {
    die(json_encode(["success" => false, "message" => "issueId and comment are required."]));
}

// If userId provided as name string (legacy), resolve to ID
if ($userId && !is_numeric($userId)) {
    // It was passed as a name — store as author instead
    $author = $userId;
    $userId = null;
}

// If userId numeric, look up the name for the author field
if ($userId && is_numeric($userId)) {
    try {
        $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
        $uStmt->execute([':id' => $userId]);
        $user = $uStmt->fetch();
        if ($user) $author = $user['name'];
    } catch (Exception $e) {
        // Non-fatal
    }
}

if (empty($author)) {
    $author = 'Admin User';
}

try {
    $sql  = "INSERT INTO comments (issue_id, user_id, author, comment, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$issueId, $userId ?: null, $author, $comment]);

    echo json_encode(["success" => true, "message" => "Comment saved successfully!"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]);
}
?>
