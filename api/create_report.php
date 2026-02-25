<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);
$dateRange = $data['dateRange'] ?? 'Last 30 Days';
$statusFilter = $data['statusFilter'] ?? 'All Statuses';

try {
    // 1. Get stats from the issues table
    $stmt = $pdo->query("SELECT 
        COUNT(*) as totalIssues,
        SUM(CASE WHEN state = 'New' THEN 1 ELSE 0 END) as newCount,
        SUM(CASE WHEN state = 'Bug' THEN 1 ELSE 0 END) as bugCount,
        SUM(CASE WHEN state = 'Open' THEN 1 ELSE 0 END) as openCount,
        SUM(CASE WHEN state = 'In Progress' THEN 1 ELSE 0 END) as inProgressCount,
        SUM(CASE WHEN state = 'Resolved' THEN 1 ELSE 0 END) as resolvedCount
    FROM issues");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. UPDATED QUERY: Using underscores (date_range, status_filter, etc.)
    // These names must match your phpMyAdmin columns exactly!
    $insertQuery = "INSERT INTO reports (
        date_range, status_filter, total_issues, 
        new_count, bug_count, open_count, in_progress_count, resolved_count, generated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([
        $dateRange, 
        $statusFilter, 
        $stats['totalIssues'],
        $stats['newCount'] ?? 0, 
        $stats['bugCount'] ?? 0, 
        $stats['openCount'] ?? 0, 
        $stats['inProgressCount'] ?? 0, 
        $stats['resolvedCount'] ?? 0
    ]);

    $stats['id'] = $pdo->lastInsertId();
    echo json_encode(["success" => true, "data" => $stats]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}