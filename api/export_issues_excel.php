<?php
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

$state    = $_GET['state']    ?? '';
$priority = $_GET['priority'] ?? '';
$search   = $_GET['search']   ?? '';

try {
    $sql = "SELECT i.id, i.dashboard, i.module, i.description, i.state, i.status,
                   i.priority, i.story_points, i.iteration_path, i.area_path,
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

    // ── Build spreadsheet ──────────────────────────────────────
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Issues');

    // ── Header row styling ─────────────────────────────────────
    $headers = [
        'A' => 'ID',
        'B' => 'Dashboard',
        'C' => 'Module',
        'D' => 'Description',
        'E' => 'State',
        'F' => 'Status',
        'G' => 'Priority',
        'H' => 'Story Points',
        'I' => 'Sprint',
        'J' => 'Area Path',
        'K' => 'Issued By',
        'L' => 'Assigned To',
        'M' => 'Date Identified',
        'N' => 'Source',
        'O' => 'Created At',
    ];

    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '1', $label);
    }

    // Header style — red background, white bold text
    $headerStyle = [
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CC0000']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ];
    $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(22);

    // ── Data rows ──────────────────────────────────────────────
    $row = 2;
    foreach ($issues as $i) {
        $sheet->setCellValue('A' . $row, $i['id']);
        $sheet->setCellValue('B' . $row, $i['dashboard']        ?? '');
        $sheet->setCellValue('C' . $row, $i['module']           ?? '');
        $sheet->setCellValue('D' . $row, $i['description']      ?? '');
        $sheet->setCellValue('E' . $row, $i['state']            ?? '');
        $sheet->setCellValue('F' . $row, $i['status']           ?? 'In Progress');
        $sheet->setCellValue('G' . $row, $i['priority']         ?? '');
        $sheet->setCellValue('H' . $row, $i['story_points']     ?? '');
        $sheet->setCellValue('I' . $row, $i['iteration_path']   ?? '');
        $sheet->setCellValue('J' . $row, $i['area_path']        ?? '');
        $sheet->setCellValue('K' . $row, $i['issued_by_name']   ?? '');
        $sheet->setCellValue('L' . $row, $i['assigned_to_name'] ?? '');
        $sheet->setCellValue('M' . $row, $i['date_identified']  ?? '');
        $sheet->setCellValue('N' . $row, $i['source']           ?? '');
        $sheet->setCellValue('O' . $row, $i['created_at']       ?? '');

        // Alternate row shading
        if ($row % 2 === 0) {
            $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF5F5']],
            ]);
        }

        $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $row++;
    }

    // ── Column widths ──────────────────────────────────────────
    $widths = ['A'=>6,'B'=>12,'C'=>12,'D'=>45,'E'=>14,'F'=>14,'G'=>12,
               'H'=>10,'I'=>12,'J'=>14,'K'=>16,'L'=>16,'M'=>16,'N'=>12,'O'=>18];
    foreach ($widths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

    // Wrap description column
    $sheet->getStyle('D2:D' . ($row - 1))->getAlignment()->setWrapText(true);

    // Freeze header row
    $sheet->freezePane('A2');

    // ── Output ─────────────────────────────────────────────────
    $filename = 'IssueTracker_Export_' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (Exception $e) {
    http_response_code(500);
    echo "Export error: " . $e->getMessage();
}
?>
