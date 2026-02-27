<?php
// Strict error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Path check
$dbPath = dirname(__DIR__) . '/config/database.php';
if (!file_exists($dbPath)) {
    die(json_encode(["success" => false, "message" => "Database file missing at: $dbPath"]));
}

require_once $dbPath;

// Input capture
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    die(json_encode(["success" => false, "message" => "Invalid JSON input received."]));
}

$issueId = $data['issueId'] ?? null;
$author  = $data['author'] ?? 'Admin User';
$comment = $data['comment'] ?? '';

if (!$issueId || empty($comment)) {
    die(json_encode(["success" => false, "message" => "Missing required fields."]));
}

try {
    // We are using the $pdo variable from your database.php
    $sql = "INSERT INTO comments (issue_id, author, comment, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$issueId, $author, $comment]);

    echo json_encode(["success" => true, "message" => "Comment saved successfully!"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]);
}
?>