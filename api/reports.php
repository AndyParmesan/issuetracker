<?php
// ============================================================
//  api/reports.php  —  Reports API + Excel Export
//  Mirrors: ReportsController.cs + ReportService.cs
//  Requires: composer require phpoffice/phpspreadsheet
// ============================================================

if (!function_exists('ok')) require_once __DIR__ . '/index.php';

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));

// Find index of 'reports' in segments
$rIdx = array_search('reports', $segments);
$next = $segments[$rIdx + 1] ?? null;
$after = $segments[$rIdx + 2] ?? null;

// Route: GET /reports/export-issues
if ($method === 'GET' && $next === 'export-issues') {
    exportIssuesExcel();
    exit;
}
// Route: GET /reports/{id}/export
if ($method === 'GET' && is_numeric($next) && $after === 'export') {
    exportReportExcel((int)$next);
    exit;
}
// Route: GET /reports
if ($method === 'GET' && $next === null) {
    getAllReports();
    exit;
}
// Route: POST /reports/generate
if ($method === 'POST' && $next === 'generate') {
    generateReport();
    exit;
}

fail('Endpoint not found.', 404);

// ── Get All Reports ───────────────────────────────────────────

function getAllReports(): void {
    $pdo  = getDB();
    $stmt = $pdo->query("SELECT r.*, u.name AS generated_by_name
                         FROM reports r
                         LEFT JOIN users u ON r.generated_by = u.id
                         ORDER BY r.generated_at DESC
                         LIMIT 50");
    $rows = $stmt->fetchAll();
    ok(array_map('formatReport', $rows));
}

// ── Generate Report ───────────────────────────────────────────

function generateReport(): void {
    $pdo  = getDB();
    $body = bodyJson();

    $dateRange    = $body['dateRange']    ?? 'Last 30 Days';
    $statusFilter = $body['statusFilter'] ?? 'All Statuses';
    $generatedBy  = isset($body['generatedBy']) ? (int)$body['generatedBy'] : null;

    // Build date filter
    $dateWhere = buildDateWhere($dateRange);
    $stateWhere = ($statusFilter !== 'All Statuses') ? " AND state = " . $pdo->quote($statusFilter) : '';

    $where = "WHERE 1=1 $dateWhere $stateWhere";

    $counts = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(state='New') AS new_count,
        SUM(state='Bug') AS bug_count,
        SUM(state='Open') AS open_count,
        SUM(state='In Progress') AS in_progress_count,
        SUM(state='Resolved') AS resolved_count
        FROM issues $where")->fetch();

    $stmt = $pdo->prepare("INSERT INTO reports 
        (generated_by, date_range, status_filter, total_issues, new_count, bug_count, open_count, in_progress_count, resolved_count, generated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmt->execute([
        $generatedBy,
        $dateRange,
        $statusFilter,
        (int)$counts['total'],
        (int)$counts['new_count'],
        (int)$counts['bug_count'],
        (int)$counts['open_count'],
        (int)$counts['in_progress_count'],
        (int)$counts['resolved_count'],
    ]);

    $newId = (int)$pdo->lastInsertId();
    $row   = $pdo->prepare("SELECT r.*, u.name AS generated_by_name
                             FROM reports r LEFT JOIN users u ON r.generated_by = u.id
                             WHERE r.id = ?");
    $row->execute([$newId]);
    ok(formatReport($row->fetch()), 'Report generated successfully.');
}

// ── Export Report to Excel ────────────────────────────────────

function exportReportExcel(int $id): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT r.*, u.name AS generated_by_name FROM reports r
                           LEFT JOIN users u ON r.generated_by = u.id WHERE r.id = ?");
    $stmt->execute([$id]);
    $report = $stmt->fetch();
    if (!$report) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Report not found.']); exit; }

    $dateWhere  = buildDateWhere($report['date_range'] ?? 'Last 30 Days');
    $stateWhere = ($report['status_filter'] !== 'All Statuses') ? " AND state = " . $pdo->quote($report['status_filter']) : '';

    $issues = $pdo->query("SELECT i.*, ib.name AS issued_by_name, at.name AS assigned_to_name
                           FROM issues i
                           LEFT JOIN users ib ON i.issued_by = ib.id
                           LEFT JOIN users at ON i.assigned_to = at.id
                           WHERE 1=1 $dateWhere $stateWhere
                           ORDER BY i.created_at DESC")->fetchAll();

    streamExcel($issues, "Report_{$id}_" . date('Y-m-d'));
}

// ── Export Issues to Excel ────────────────────────────────────

function exportIssuesExcel(): void {
    $pdo    = getDB();
    $state    = $_GET['state']    ?? '';
    $priority = $_GET['priority'] ?? '';

    $sql    = "SELECT i.*, ib.name AS issued_by_name, at.name AS assigned_to_name
               FROM issues i
               LEFT JOIN users ib ON i.issued_by = ib.id
               LEFT JOIN users at ON i.assigned_to = at.id
               WHERE 1=1";
    $params = [];
    if ($state !== '')    { $sql .= " AND i.state = ?";    $params[] = $state; }
    if ($priority !== '') { $sql .= " AND i.priority = ?"; $params[] = $priority; }
    $sql .= " ORDER BY i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $issues = $stmt->fetchAll();

    streamExcel($issues, "Issues_Export_" . date('Y-m-d'));
}

// ── Excel Stream Helper ───────────────────────────────────────

function streamExcel(array $issues, string $filename): void {
    // Try PhpSpreadsheet if available (composer require phpoffice/phpspreadsheet)
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        buildWithSpreadsheet($issues, $filename);
        return;
    }

    // Fallback: plain CSV download
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"$filename.csv\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Dashboard','Module','Description','State','Priority','Issued By','Assigned To','Date Identified','Source','Created At']);
    foreach ($issues as $i) {
        fputcsv($out, [
            $i['id'], $i['dashboard'], $i['module'], $i['description'],
            $i['state'], $i['priority'],
            $i['issued_by_name'] ?? '', $i['assigned_to_name'] ?? '',
            $i['date_identified'], $i['source'], $i['created_at']
        ]);
    }
    fclose($out);
    exit;
}

function buildWithSpreadsheet(array $issues, string $filename): void {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Issues');

    // Header row
    $headers = ['ID','Dashboard','Module','Description','State','Priority','Issued By','Assigned To','Date Identified','Source','Created At'];
    $sheet->fromArray($headers, null, 'A1');

    // Style header
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
    ];
    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

    // Data rows
    $row = 2;
    foreach ($issues as $i) {
        $sheet->fromArray([
            $i['id'], $i['dashboard'], $i['module'], $i['description'],
            $i['state'], $i['priority'],
            $i['issued_by_name'] ?? '', $i['assigned_to_name'] ?? '',
            $i['date_identified'], $i['source'], $i['created_at']
        ], null, "A$row");
        $row++;
    }

    // Auto-width
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename.xlsx\"");
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ── Date Range Helper ─────────────────────────────────────────

function buildDateWhere(string $range): string {
    return match ($range) {
        'Last 7 Days'  => " AND date_identified >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        'Last 30 Days' => " AND date_identified >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        'Last 90 Days' => " AND date_identified >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
        'This Year'    => " AND YEAR(date_identified) = YEAR(CURDATE())",
        default        => ''
    };
}

// ── Format Helper ─────────────────────────────────────────────

function formatReport(array $r): array {
    return [
        'id'               => (int)$r['id'],
        'generatedBy'      => $r['generated_by'] ? (int)$r['generated_by'] : null,
        'generatedByName'  => $r['generated_by_name'] ?? null,
        'dateRange'        => $r['date_range'],
        'statusFilter'     => $r['status_filter'],
        'totalIssues'      => (int)$r['total_issues'],
        'newCount'         => (int)$r['new_count'],
        'bugCount'         => (int)$r['bug_count'],
        'openCount'        => (int)$r['open_count'],
        'inProgressCount'  => (int)$r['in_progress_count'],
        'resolvedCount'    => (int)$r['resolved_count'],
        'generatedAt'      => $r['generated_at'],
    ];
}
