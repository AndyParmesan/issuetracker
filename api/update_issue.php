<?php
require_once '../config/database.php';

// Get ID from URL parameter (e.g., update_issue.php?id=10)
$id = $_GET['id'] ?? null;
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$id || !$data) {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit;
}

try {
    // Dynamically build the update statement similar to IssueService.cs
    $fields = [];
    $params = [':id' => $id];

    if (isset($data['state'])) { $fields[] = "state = :state"; $params[':state'] = $data['state']; }
    if (isset($data['priority'])) { $fields[] = "priority = :priority"; $params[':priority'] = $data['priority']; }
    if (isset($data['assignedTo'])) { $fields[] = "assigned_to = :assignedTo"; $params[':assignedTo'] = $data['assignedTo']; }

    if (empty($fields)) {
        echo json_encode(["success" => false, "message" => "No fields to update."]);
        exit;
    }

    $sql = "UPDATE issues SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(["success" => true, "message" => "Issue updated successfully."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>