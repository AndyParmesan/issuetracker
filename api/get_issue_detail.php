<?php
require_once '../config/database.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(["success" => false, "message" => "No ID provided."]);
    exit;
}

try {
    $sql = "SELECT 
                i.*,
                u1.name AS issued_by_name,
                u2.name AS assigned_to_name
            FROM issues i
            LEFT JOIN users u1 ON i.issued_by    = u1.id
            LEFT JOIN users u2 ON i.assigned_to  = u2.id
            WHERE i.id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $issue = $stmt->fetch();

    if ($issue) {
        // Normalize camelCase aliases for frontend compatibility
        $issue['issuedByName']   = $issue['issued_by_name'];
        $issue['assignedToName'] = $issue['assigned_to_name'];
        $issue['dateIdentified'] = $issue['date_identified'];
        $issue['storyPoints']    = $issue['story_points'];
        $issue['areaPath']       = $issue['area_path'];
        $issue['iterationPath']  = $issue['iteration_path'];
        $issue['acceptanceCriteria'] = $issue['acceptance_criteria'];

        echo json_encode(["success" => true, "data" => $issue]);
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Issue not found."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
