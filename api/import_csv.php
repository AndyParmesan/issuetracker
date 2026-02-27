<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../config/database.php';

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

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode(["success" => false, "message" => "Only CSV files are accepted."]);
    exit;
}

// Validate size (10MB max)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(["success" => false, "message" => "File exceeds 10MB limit."]);
    exit;
}

// Read the CSV
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(["success" => false, "message" => "Could not read uploaded file."]);
    exit;
}

// Skip header row
$headers = fgetcsv($handle);

$importedCount = 0;
$errors        = [];
$rowNum        = 1;

// Valid enums matching schema
$validStates    = ['New', 'Bug', 'Open', 'In Progress', 'Resolved'];
$validPriorities = ['Low', 'Medium', 'High', 'Critical'];

$stmt = $pdo->prepare(
    "INSERT INTO issues (dashboard, module, description, state, priority, issued_by, date_identified, source)
     VALUES (:dashboard, :module, :description, :state, :priority, :issuedBy, :dateIdentified, 'CSV Import')"
);

// Log the import in issue_imports table
$logStmt = $pdo->prepare(
    "INSERT INTO issue_imports (filename, imported_by, records_count) VALUES (:filename, :importedBy, :count)"
);

while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;

    // Expected CSV columns: ID, Description, State, Date Identified, Issued by
    // Description may contain "Dashboard | Module | Actual description"
    if (count($row) < 2) {
        $errors[] = "Row $rowNum: Not enough columns, skipped.";
        continue;
    }

    // Parse description field — supports "Dashboard | Module | Description" format
    $rawDesc  = trim($row[1] ?? '');
    $dashboard = null;
    $module    = null;
    $description = $rawDesc;

    if (str_contains($rawDesc, '|')) {
        $parts = array_map('trim', explode('|', $rawDesc));
        if (count($parts) >= 3) {
            $dashboard   = $parts[0];
            $module      = $parts[1];
            $description = implode(' | ', array_slice($parts, 2));
        } elseif (count($parts) === 2) {
            $dashboard   = $parts[0];
            $description = $parts[1];
        }
    }

    if (empty($description)) {
        $errors[] = "Row $rowNum: Empty description, skipped.";
        continue;
    }

    // State — default to 'New' if invalid or empty
    $rawState = trim($row[2] ?? '');
    $state    = in_array($rawState, $validStates) ? $rawState : 'New';

    // Date Identified
    $rawDate = trim($row[3] ?? '');
    $dateIdentified = null;
    if (!empty($rawDate)) {
        $parsed = date_create($rawDate);
        $dateIdentified = $parsed ? date_format($parsed, 'Y-m-d') : date('Y-m-d');
    } else {
        $dateIdentified = date('Y-m-d');
    }

    // Priority (optional col 5 if present)
    $rawPri  = trim($row[5] ?? '');
    $priority = in_array($rawPri, $validPriorities) ? $rawPri : 'Medium';

    try {
        $stmt->execute([
            ':dashboard'      => $dashboard,
            ':module'         => $module,
            ':description'    => $description,
            ':state'          => $state,
            ':priority'       => $priority,
            ':issuedBy'       => $importedBy ?: null,
            ':dateIdentified' => $dateIdentified,
        ]);
        $importedCount++;
    } catch (PDOException $e) {
        $errors[] = "Row $rowNum: DB error — " . $e->getMessage();
    }
}

fclose($handle);

// Log the import
try {
    $logStmt->execute([
        ':filename'   => $file['name'],
        ':importedBy' => $importedBy ?: null,
        ':count'      => $importedCount,
    ]);
} catch (Exception $e) {
    // Non-fatal — log silently
}

echo json_encode([
    "success" => true,
    "message" => "$importedCount issue(s) imported successfully.",
    "data"    => [
        "imported" => $importedCount,
        "errors"   => $errors
    ]
]);
?>
