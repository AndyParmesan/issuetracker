<?php
// ============================================================
//  api/import.php  —  CSV Import API
//  Mirrors: ImportController.cs + CsvImportService.cs
// ============================================================

if (!function_exists('ok')) require_once __DIR__ . '/index.php';

$method   = $_SERVER['REQUEST_METHOD'];
$segments = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));

// Only POST /import/csv is supported
if ($method === 'POST' && in_array('csv', $segments)) {
    importCsv();
} else {
    fail('Endpoint not found.', 404);
}

// ── CSV Import ────────────────────────────────────────────────

function importCsv(): void {
    $pdo = getDB();

    if (empty($_FILES['file'])) fail('No file uploaded.');

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) fail('File upload error.');
    if ($file['size'] > 10 * 1024 * 1024) fail('File exceeds 10MB limit.');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') fail('Only CSV files are supported. Please export your Excel file as CSV first.');

    $importedBy = isset($_POST['importedBy']) ? (int)$_POST['importedBy'] : null;

    // Parse CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) fail('Could not read file.');

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers) { fclose($handle); fail('CSV file is empty or invalid.'); }

    // Normalize headers (lowercase, trim)
    $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

    // Map expected columns
    // Expected: ID, Description, State, Date Identified:, Issued by:
    // Description may contain: "Dashboard | Module | Description text"
    $colMap = [];
    foreach ($headers as $i => $h) {
        $clean = preg_replace('/[^a-z0-9]/', '', $h); // strip punctuation
        if (str_contains($clean, 'description'))   $colMap['description']     = $i;
        if (str_contains($clean, 'state'))         $colMap['state']           = $i;
        if (str_contains($clean, 'dateidentified') || str_contains($clean, 'date')) $colMap['date'] = $i;
        if (str_contains($clean, 'issuedby') || str_contains($clean, 'issueby'))    $colMap['issued_by'] = $i;
        if ($clean === 'id')                       $colMap['id']              = $i;
        if (str_contains($clean, 'priority'))      $colMap['priority']        = $i;
        if (str_contains($clean, 'module'))        $colMap['module']          = $i;
        if (str_contains($clean, 'dashboard'))     $colMap['dashboard']       = $i;
    }

    $validStates    = ['New', 'Bug', 'Open', 'In Progress', 'Resolved'];
    $validPriorities = ['Critical', 'High', 'Medium', 'Low'];

    $imported = 0;
    $errors   = [];
    $row      = 1;

    $stmt = $pdo->prepare("INSERT INTO issues 
        (dashboard, module, description, state, priority, issued_by, date_identified, source, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'CSV Import', NOW(), NOW())");

    while (($data = fgetcsv($handle)) !== false) {
        $row++;
        if (count(array_filter($data)) === 0) continue; // skip blank rows

        // Get raw description
        $rawDesc = trim($data[$colMap['description'] ?? 0] ?? '');
        if ($rawDesc === '') {
            $errors[] = "Row $row: Description is empty, skipped.";
            continue;
        }

        // Parse "Dashboard | Module | Description" format
        $dashboard   = null;
        $module      = null;
        $description = $rawDesc;

        if (str_contains($rawDesc, '|')) {
            $parts = array_map('trim', explode('|', $rawDesc, 3));
            if (count($parts) === 3) {
                $dashboard   = $parts[0] ?: null;
                $module      = $parts[1] ?: null;
                $description = $parts[2];
            } elseif (count($parts) === 2) {
                $dashboard   = $parts[0] ?: null;
                $description = $parts[1];
            }
        }

        // If column map has explicit dashboard/module columns, prefer those
        if (isset($colMap['dashboard']) && !empty($data[$colMap['dashboard']])) {
            $dashboard = trim($data[$colMap['dashboard']]);
        }
        if (isset($colMap['module']) && !empty($data[$colMap['module']])) {
            $module = trim($data[$colMap['module']]);
        }

        // State
        $state = trim($data[$colMap['state'] ?? -1] ?? 'New');
        if (!in_array($state, $validStates)) {
            $errors[] = "Row $row: Invalid state '$state', defaulted to 'New'.";
            $state = 'New';
        }

        // Priority
        $priority = trim($data[$colMap['priority'] ?? -1] ?? 'Medium');
        if (!in_array($priority, $validPriorities)) {
            $priority = 'Medium';
        }

        // Date
        $dateRaw = trim($data[$colMap['date'] ?? -1] ?? '');
        $date    = null;
        if ($dateRaw !== '') {
            $parsed = strtotime($dateRaw);
            $date   = $parsed ? date('Y-m-d', $parsed) : null;
        }
        $date = $date ?? date('Y-m-d');

        // Issued by name → look up user ID
        $issuedById = null;
        $issuedByRaw = trim($data[$colMap['issued_by'] ?? -1] ?? '');
        if ($issuedByRaw !== '' && $importedBy === null) {
            $u = $pdo->prepare("SELECT id FROM users WHERE name LIKE ? LIMIT 1");
            $u->execute(["%$issuedByRaw%"]);
            $found = $u->fetchColumn();
            if ($found) $issuedById = (int)$found;
        } elseif ($importedBy) {
            $issuedById = $importedBy;
        }

        try {
            $stmt->execute([$dashboard, $module, $description, $state, $priority, $issuedById, $date]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "Row $row: DB error — " . $e->getMessage();
        }
    }

    fclose($handle);

    // Log the import — table: issue_imports (filename, imported_by, records_count, imported_at)
    try {
        $log = $pdo->prepare("INSERT INTO issue_imports (filename, imported_by, records_count, imported_at)
                              VALUES (?, ?, ?, NOW())");
        $log->execute([$file['name'], $importedBy, $imported]);
    } catch (PDOException $e) {
        // Non-fatal — import succeeded even if log fails
    }

    if ($imported === 0 && !empty($errors)) {
        fail('Import failed: ' . $errors[0]);
    }

    $message = !empty($errors)
        ? "Imported $imported records with " . count($errors) . " error(s)."
        : "Successfully imported $imported records.";

    ok([
        'importedCount' => $imported,
        'errors'        => $errors,
        'hasErrors'     => !empty($errors),
    ], $message);
}
