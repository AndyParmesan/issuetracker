<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "POST method required."]);
    exit;
}

if (empty($_FILES['file'])) {
    echo json_encode(["success" => false, "message" => "No file uploaded."]);
    exit;
}

$importedBy = $_POST['importedBy'] ?? null;
$file       = $_FILES['file'];

// Validate extension — accept xlsx and xls
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'])) {
    echo json_encode(["success" => false, "message" => "Only .xlsx or .xls files are accepted."]);
    exit;
}

// Validate size (10MB max)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(["success" => false, "message" => "File exceeds 10MB limit."]);
    exit;
}

try {
    // Load spreadsheet
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet       = $spreadsheet->getActiveSheet();
    $rows        = $sheet->toArray(null, true, true, false); // 0-indexed array

    if (empty($rows) || count($rows) < 2) {
        echo json_encode(["success" => false, "message" => "File is empty or has no data rows."]);
        exit;
    }

    // Skip header row (index 0)
    array_shift($rows);

    $validStates     = ['New','Bug','Open','In Progress','Resolved',
                        'Draft','For Review','Approved','In Development',
                        'For Testing','QA Failed','For UAT',
                        'Ready for Deployment','Deployed','Closed'];
    $validPriorities = ['1-Urgent','2-Critical','3-High','4-Medium','5-Low',
                        'Low','Medium','High','Critical'];

    $stmt = $pdo->prepare(
        "INSERT INTO issues
            (dashboard, module, description, state, status, priority,
             issued_by, date_identified, source)
         VALUES
            (:dashboard, :module, :description, :state, :status, :priority,
             :issuedBy, :dateIdentified, 'XLSX Import')"
    );

    $logStmt = $pdo->prepare(
        "INSERT INTO issue_imports (filename, imported_by, records_count)
         VALUES (:filename, :importedBy, :count)"
    );

    $importedCount = 0;
    $errors        = [];
    $rowNum        = 1;

    foreach ($rows as $row) {
        $rowNum++;

        // Expected columns (0-based):
        // 0=ID(skip), 1=Dashboard, 2=Module, 3=Description,
        // 4=State, 5=Status, 6=Priority, 7=SP, 8=Sprint,
        // 9=Area Path, 10=Issued By, 11=Assigned To, 12=Date Identified

        // Support old CSV format too (no dashboard/module columns)
        // Detect by checking if col 3 has pipe-separated content
        $rawDesc   = trim($row[3] ?? $row[1] ?? '');
        $dashboard = trim($row[1] ?? '') ?: null;
        $module    = trim($row[2] ?? '') ?: null;

        // Fallback: if description has pipe format, parse it
        if (str_contains($rawDesc, '|')) {
            $parts = array_map('trim', explode('|', $rawDesc));
            if (count($parts) >= 3) {
                $dashboard = $parts[0];
                $module    = $parts[1];
                $rawDesc   = implode(' | ', array_slice($parts, 2));
            } elseif (count($parts) === 2) {
                $dashboard = $parts[0];
                $rawDesc   = $parts[1];
            }
        }

        if (empty($rawDesc)) {
            $errors[] = "Row {$rowNum}: Empty description, skipped.";
            continue;
        }

        // State
        $rawState = trim($row[4] ?? '');
        $state    = in_array($rawState, $validStates) ? $rawState : 'New';

        // Status — default In Progress for new issues
        $rawStatus = trim($row[5] ?? '');
        $status    = in_array($rawStatus, ['In Progress','Fixed','Resolved']) ? $rawStatus : 'In Progress';

        // Priority
        $rawPri   = trim($row[6] ?? '');
        $priority = in_array($rawPri, $validPriorities) ? $rawPri : '4-Medium';

        // Date Identified
        $rawDate = trim($row[12] ?? $row[3] ?? '');
        $dateIdentified = date('Y-m-d');
        if (!empty($rawDate)) {
            if (is_numeric($rawDate)) {
                // Excel serial date
                $dateIdentified = date('Y-m-d', PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($rawDate));
            } else {
                $parsed = date_create($rawDate);
                if ($parsed) $dateIdentified = date_format($parsed, 'Y-m-d');
            }
        }

        try {
            $stmt->execute([
                ':dashboard'      => $dashboard,
                ':module'         => $module,
                ':description'    => $rawDesc,
                ':state'          => $state,
                ':status'         => $status,
                ':priority'       => $priority,
                ':issuedBy'       => $importedBy ?: null,
                ':dateIdentified' => $dateIdentified,
            ]);
            $importedCount++;
        } catch (PDOException $e) {
            $errors[] = "Row {$rowNum}: DB error — " . $e->getMessage();
        }
    }

    // Log the import
    try {
        $logStmt->execute([
            ':filename'   => $file['name'],
            ':importedBy' => $importedBy ?: null,
            ':count'      => $importedCount,
        ]);
    } catch (Exception $e) {
        // Non-fatal
    }

    echo json_encode([
        "success" => true,
        "message" => "{$importedCount} issue(s) imported successfully.",
        "data"    => [
            "imported" => $importedCount,
            "errors"   => $errors
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error reading file: " . $e->getMessage()]);
}
?>
