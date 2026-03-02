<?php
require_once '../config/database.php';

try {
    // Total = ALL issues regardless of state (#7 fix — matches All Issues tab count)
    $stmt = $pdo->query("SELECT COUNT(*) FROM issues");
    $totalIssues = (int)$stmt->fetchColumn();

    // Count by state grouping
    $stmt = $pdo->query("SELECT state, COUNT(*) as cnt FROM issues GROUP BY state");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['state']] = (int)$row['cnt'];
    }

    $get = fn($k) => $counts[$k] ?? 0;

    $newCount        = $get('New') + $get('Draft') + $get('For Review') + $get('Approved');
    $bugCount        = $get('Bug') + $get('QA Failed');
    $openCount       = $get('Open');
    $inProgressCount = $get('In Progress') + $get('In Development') + $get('For Testing') +
                       $get('For UAT') + $get('Ready for Deployment');
    $resolvedCount   = $get('Resolved') + $get('Deployed') + $get('Closed');

    // Issues by priority
    $stmt = $pdo->query("SELECT priority, COUNT(*) as count FROM issues GROUP BY priority ORDER BY priority");
    $byPriority = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Issues by dashboard
    $stmt = $pdo->query("SELECT COALESCE(dashboard, 'Unknown') as dashboard, COUNT(*) as count FROM issues GROUP BY dashboard ORDER BY count DESC LIMIT 10");
    $byDashboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => [
            "totalIssues"     => $totalIssues,
            "newCount"        => $newCount,
            "bugCount"        => $bugCount,
            "openCount"       => $openCount,
            "inProgressCount" => $inProgressCount,
            "resolvedCount"   => $resolvedCount,
            "byPriority"      => $byPriority,
            "byDashboard"     => $byDashboard,
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]);
}
?>
