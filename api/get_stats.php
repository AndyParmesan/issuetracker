<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
require_once '../config/database.php';

try {
    $type = $_GET['type'] ?? 'items'; // 'items' or 'stories'

    if ($type === 'stories' || $type === 'user_story') {
        $where = "WHERE source = 'user_story'";
    } else {
        $where = "WHERE (source != 'user_story' OR source IS NULL OR source = 'Manual' OR source = 'XLSX Import')";
    }

    // Total
    $stmt = $pdo->query("SELECT COUNT(*) FROM issues $where");
    $total = (int)$stmt->fetchColumn();

    // Count by state
    $stmt = $pdo->query("SELECT state, COUNT(*) as cnt FROM issues $where GROUP BY state");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['state']] = (int)$row['cnt'];
    }
    $get = fn($k) => $counts[$k] ?? 0;

    if ($type === 'stories' || $type === 'user_story') {
        $data = [
            'total'            => $total,
            'draftCount'       => $get('Draft'),
            'forReviewCount'   => $get('For Review'),
            'approvedCount'    => $get('Approved'),
            'inDevCount'       => $get('In Development'),
            'testingCount'     => $get('For Testing'),
            'qaFailedCount'    => $get('QA Failed'),
            'forUatCount'      => $get('For UAT'),
            'readyDeployCount' => $get('Ready for Deployment'),
            'deployedCount'    => $get('Deployed'),
            'closedCount'      => $get('Closed'),
        ];
    } else {
        $data = [
            'total'              => $total,
            'newCount'           => $get('New'),
            'bugCount'           => $get('Bug'),
            'openCount'          => $get('Open'),
            'inProgressCount'    => $get('In Progress'),
            'asDesignedCount'    => $get('As Designed'),
            'enhancementCount'   => $get('Enhancement'),
            'needsClarifCount'   => $get('Needs Clarification'),
            'monitoringCount'    => $get('Monitoring'),
            'closedCount'        => $get('Closed') + $get('Resolved'),
        ];
    }

    // By priority for chart
    $stmt = $pdo->query("SELECT priority, COUNT(*) as count FROM issues $where GROUP BY priority ORDER BY priority");
    $data['byPriority'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>