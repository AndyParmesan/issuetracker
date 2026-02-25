<?php
require_once '../config/database.php';

$reportId = $_GET['id'] ?? null;

if (!$reportId) {
    die("Report ID is required.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        die("Report not found.");
    }

    // Force download as CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Issue_Tracker_Report_#' . $reportId . '.csv"');

    $output = fopen('php://output', 'w');
    
    // --- SECTION 1: REPORT HEADER ---
    fputcsv($output, ['ISSUE TRACKER ENTERPRISE - SUMMARY REPORT']);
    fputcsv($output, ['Generated on:', $report['generated_at'] ?? $report['generatedAt']]);
    fputcsv($output, []); // Empty row for spacing

    // --- SECTION 2: FILTERS APPLIED ---
    fputcsv($output, ['REPORT CRITERIA']);
    fputcsv($output, ['Date Range:', $report['date_range'] ?? $report['dateRange']]);
    fputcsv($output, ['Status Filter:', $report['status_filter'] ?? $report['statusFilter']]);
    fputcsv($output, []); // Empty row for spacing

    // --- SECTION 3: DATA SUMMARY ---
    fputcsv($output, ['ISSUE STATISTICS']);
    fputcsv($output, ['Metric', 'Count']);
    fputcsv($output, ['Total Issues Identifed', $report['total_issues'] ?? $report['totalIssues']]);
    fputcsv($output, ['New Issues', $report['new_count'] ?? $report['newCount']]);
    fputcsv($output, ['Bugs Reported', $report['bug_count'] ?? $report['bugCount']]);
    fputcsv($output, ['Open Issues', $report['open_count'] ?? $report['openCount']]);
    fputcsv($output, ['Issues In Progress', $report['in_progress_count'] ?? $report['inProgressCount']]);
    fputcsv($output, ['Resolved Issues', $report['resolved_count'] ?? $report['resolvedCount']]);

    fclose($output);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>