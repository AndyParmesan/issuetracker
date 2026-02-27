<?php
require_once '../config/database.php';

$state    = $_GET['state']    ?? '';
$priority = $_GET['priority'] ?? '';
$search   = $_GET['search']   ?? '';

try {
    $sql = "SELECT i.id, i.dashboard, i.module, i.description, i.state, i.priority,
                   i.source, i.date_identified, i.created_at,
                   u1.name AS issued_by_name, u2.name AS assigned_to_name
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
        $sql .= " AND (i.description LIKE :search OR i.dashboard LIKE :search OR i.module LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $issues = $stmt->fetchAll();

    // Output as CSV (Excel-compatible)
    $filename = 'IssueTracker_Export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header row
    fputcsv($output, [
        'ID', 'Dashboard', 'Module', 'Description',
        'State', 'Priority', 'Issued By', 'Assigned To',
        'Date Identified', 'Source', 'Created At'
    ]);

    // Data rows
    foreach ($issues as $i) {
        fputcsv($output, [
            $i['id'],
            $i['dashboard']        ?? '',
            $i['module']           ?? '',
            $i['description'],
            $i['state'],
            $i['priority'],
            $i['issued_by_name']   ?? '',
            $i['assigned_to_name'] ?? '',
            $i['date_identified']  ?? '',
            $i['source']           ?? '',
            $i['created_at']       ?? ''
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    http_response_code(500);
    echo "Export error: " . $e->getMessage();
}
?>
