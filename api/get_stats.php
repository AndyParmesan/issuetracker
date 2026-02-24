<?php
require_once '../config/database.php';

try {
    // 1. Status Counts (Direct conversion of Case statements in C#)
    $stmt = $pdo->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN state='New' THEN 1 ELSE 0 END) AS new_cnt,
        SUM(CASE WHEN state='Bug' THEN 1 ELSE 0 END) AS bug_cnt,
        SUM(CASE WHEN state='Open' THEN 1 ELSE 0 END) AS open_cnt,
        SUM(CASE WHEN state='In Progress' THEN 1 ELSE 0 END) AS prog_cnt,
        SUM(CASE WHEN state='Resolved' THEN 1 ELSE 0 END) AS res_cnt
        FROM issues");
    $row = $stmt->fetch();

    // 2. Priority Distribution (For Doughnut Chart)
    $priStmt = $pdo->query("SELECT priority, COUNT(*) AS count 
                            FROM issues 
                            GROUP BY priority 
                            ORDER BY FIELD(priority,'Critical','High','Medium','Low')");
    $byPriority = $priStmt->fetchAll();

    // 3. Dashboard Distribution (For Bar Chart)
    $dashStmt = $pdo->query("SELECT IFNULL(dashboard,'Unknown') AS dashboard, COUNT(*) AS count 
                             FROM issues 
                             GROUP BY dashboard 
                             ORDER BY count DESC LIMIT 8");
    $byDashboard = $dashStmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => [
            "totalIssues"     => (int)$row['total'],
            "newCount"        => (int)$row['new_cnt'],
            "bugCount"        => (int)$row['bug_cnt'],
            "openCount"       => (int)$row['open_cnt'],
            "inProgressCount" => (int)$row['prog_cnt'],
            "resolvedCount"   => (int)$row['res_cnt'],
            "byPriority"      => $byPriority,
            "byDashboard"     => $byDashboard
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>