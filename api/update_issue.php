<?php
require_once '../config/database.php';

// ID comes from ?id=X query param (works with both POST and PUT)
$id   = $_GET['id'] ?? null;
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$id || !$data) {
    echo json_encode(["success" => false, "message" => "Invalid request. Provide ?id=X and JSON body."]);
    exit;
}

try {
    $fields = [];
    $params = [':id' => $id];

    // Original fields
    if (isset($data['state']))          { $fields[] = "state = :state";                   $params[':state']         = $data['state']; }
    if (isset($data['priority']))       { $fields[] = "priority = :priority";             $params[':priority']      = $data['priority']; }
    if (isset($data['assignedTo']))     { $fields[] = "assigned_to = :assignedTo";        $params[':assignedTo']    = $data['assignedTo'] ?: null; }

    // New PDF-aligned fields
    if (isset($data['title']))               { $fields[] = "title = :title";                           $params[':title']              = $data['title']; }
    if (isset($data['storyPoints']))         { $fields[] = "story_points = :storyPoints";              $params[':storyPoints']        = $data['storyPoints'] ?: null; }
    if (isset($data['areaPath']))            { $fields[] = "area_path = :areaPath";                    $params[':areaPath']           = $data['areaPath']; }
    if (isset($data['iterationPath']))       { $fields[] = "iteration_path = :iterationPath";          $params[':iterationPath']      = $data['iterationPath']; }
    if (isset($data['acceptanceCriteria'])) { $fields[] = "acceptance_criteria = :acceptanceCriteria"; $params[':acceptanceCriteria'] = $data['acceptanceCriteria']; }
    if (isset($data['description']))        { $fields[] = "description = :description";               $params[':description']        = $data['description']; }
    if (isset($data['dashboard']))          { $fields[] = "dashboard = :dashboard";                   $params[':dashboard']          = $data['dashboard']; }
    if (isset($data['module']))             { $fields[] = "module = :module";                         $params[':module']             = $data['module']; }

    if (empty($fields)) {
        echo json_encode(["success" => false, "message" => "No fields to update."]);
        exit;
    }

    $sql  = "UPDATE issues SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(["success" => true, "message" => "Issue updated successfully."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
