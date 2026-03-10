<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
require_once '../config/database.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['description'])) {
        echo json_encode(["success" => false, "message" => "Description is required."]);
        exit;
    }

    $sql = "INSERT INTO issues 
                (title, dashboard, particular_id, module, description, state, status, priority,
                 story_points, area_path, iteration_path, acceptance_criteria,
                 issued_by, assigned_to, date_identified, source)
            VALUES 
                (:title, :dashboard, :particularId, :module, :description, :state, :status, :priority,
                 :storyPoints, :areaPath, :iterationPath, :acceptanceCriteria,
                 :issuedBy, :assignedTo, :dateIdentified, 'Manual')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title'               => $data['title']              ?? null,
        ':dashboard'           => $data['dashboard']          ?? null,
        ':particularId'        => $data['particularId']       ?? null,
        ':module'              => $data['module']             ?? null,
        ':description'         => $data['description'],
        ':state'               => $data['state']              ?? 'New',
        ':status'              => 'In Progress',
        ':priority'            => $data['priority']           ?? '4-Medium',
        ':storyPoints'         => $data['storyPoints']        ?? null,
        ':areaPath'            => $data['areaPath']           ?? null,
        ':iterationPath'       => $data['iterationPath']      ?? null,
        ':acceptanceCriteria'  => $data['acceptanceCriteria'] ?? null,
        ':issuedBy'            => $data['issuedBy']           ?? null,
        ':assignedTo'          => $data['assignedTo']         ?? null,
        ':dateIdentified'      => $data['dateIdentified']     ?? date('Y-m-d'),
    ]);

    $newId = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "message" => "Issue created successfully.",
        "data"    => ["id" => $newId]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
