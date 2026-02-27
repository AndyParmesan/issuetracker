<?php
require_once '../config/database.php';

try {
    $state    = $_GET['state']    ?? '';
    $priority = $_GET['priority'] ?? '';
    $search   = $_GET['search']   ?? '';

    $sql = "SELECT 
                i.id,
                i.title,
                i.dashboard,
                i.module,
                i.description,
                i.state,
                i.priority,
                i.story_points,
                i.area_path,
                i.iteration_path,
                i.acceptance_criteria,
                i.source,
                i.date_identified,
                i.created_at,
                i.updated_at,
                u1.name AS issued_by_name,
                u2.name AS assigned_to_name
            FROM issues i
            LEFT JOIN users u1 ON i.issued_by   = u1.id
            LEFT JOIN users u2 ON i.assigned_to = u2.id
            WHERE 1=1";

    $params = [];

    if (!empty($state)) {
        $sql .= " AND i.state = :state";
        $params[':state'] = $state;
    }
    if (!empty($priority)) {
        $sql .= " AND i.priority = :priority";
        $params[':priority'] = $priority;
    }
    if (!empty($search)) {
        $sql .= " AND (i.description LIKE :search OR i.title LIKE :search OR i.dashboard LIKE :search OR i.module LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $issues = $stmt->fetchAll();

    echo json_encode(["success" => true, "data" => $issues]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
