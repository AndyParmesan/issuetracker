<?php

// api/create_issue.php
require_once '../config/database.php';

try {
    // 1. Get JSON data from the frontend
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['description'])) {
        echo json_encode(["success" => false, "message" => "Description is required."]);
        exit;
    }

    // 2. Prepare the SQL (Conversion of IssueService.cs logic)
    $sql = "INSERT INTO issues (dashboard, module, description, state, priority, issued_by, assigned_to, date_identified, source) 
            VALUES (:dashboard, :module, :description, :state, :priority, :issuedBy, :assignedTo, :dateIdentified, 'Manual')";
    
    $stmt = $pdo->prepare($sql);

    // 3. Execute with data from the Frontend DTO
    $stmt->execute([
        ':dashboard'      => $data['dashboard'] ?? null,
        ':module'         => $data['module'] ?? null,
        ':description'    => $data['description'],
        ':state'          => $data['state'] ?? 'New',
        ':priority'       => $data['priority'] ?? 'Medium',
        ':issuedBy'       => $data['issuedBy'] ?? null,
        ':assignedTo'     => $data['assignedTo'] ?? null,
        ':dateIdentified' => $data['dateIdentified'] ?? date('Y-m-d')
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