<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
require_once '../config/database.php';

try {
    $state      = $_GET['state']      ?? '';
    $priority   = $_GET['priority']   ?? '';
    $search     = $_GET['search']     ?? '';
    $particular = $_GET['particular'] ?? '';

    $sql = "SELECT 
                i.id,
                i.title,
                i.dashboard,
                i.particular_id,
                p.name          AS particular_name,
                i.module,
                i.description,
                i.state,
                i.status,
                i.priority,
                i.story_points,
                i.area_path,
                i.iteration_path,
                i.acceptance_criteria,
                i.source,
                i.date_identified,
                i.created_at,
                i.updated_at,
                i.issued_by,
                i.assigned_to,
                u1.name AS issued_by_name,
                u2.name AS assigned_to_name
            FROM issues i
            LEFT JOIN users       u1 ON i.issued_by      = u1.id
            LEFT JOIN users       u2 ON i.assigned_to    = u2.id
            LEFT JOIN particulars p  ON i.particular_id  = p.particular_id
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
    if (!empty($particular)) {
        $sql .= " AND i.particular_id = :particular";
        $params[':particular'] = $particular;
    }
    if (!empty($search)) {
$sql .= " AND (CAST(i.id AS CHAR) LIKE :search OR i.description LIKE :search OR i.title LIKE :search OR i.dashboard LIKE :search OR i.module LIKE :search OR p.name LIKE :search)";        $params[':search'] = "%$search%";
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
