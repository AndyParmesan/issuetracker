<?php
// ============================================================
//  api/attachments.php  —  File Attachments API
//  Mirrors: AttachmentsController.cs
//  Uploads stored in: /uploads/
// ============================================================

if (!function_exists('ok')) require_once __DIR__ . '/index.php';

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_ATTACHMENTS', 5);
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// ── Entry Points ─────────────────────────────────────────────

function handleAttachments(int $issueId): void {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET')  { getAttachments($issueId);    return; }
    if ($method === 'POST') { uploadAttachment($issueId);  return; }
    fail('Method not allowed.', 405);
}

function handleAttachment(int $issueId, int $attId): void {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'DELETE') { deleteAttachment($attId); return; }
    fail('Method not allowed.', 405);
}

function handleDownload(int $issueId, int $attId): void {
    downloadAttachment($attId);
}

// ── Get All Attachments for Issue ─────────────────────────────

function getAttachments(int $issueId): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE issue_id = ? ORDER BY uploaded_at ASC");
    $stmt->execute([$issueId]);
    $rows = $stmt->fetchAll();
    ok(array_map('formatAttachment', $rows));
}

// ── Upload ────────────────────────────────────────────────────

function uploadAttachment(int $issueId): void {
    $pdo = getDB();

    // Check attachment count (max 5)
    $count = $pdo->prepare("SELECT COUNT(*) FROM attachments WHERE issue_id = ?");
    $count->execute([$issueId]);
    if ((int)$count->fetchColumn() >= MAX_ATTACHMENTS) {
        fail('Maximum of 5 attachments allowed per issue.');
    }

    if (empty($_FILES['file'])) fail('No file uploaded.');

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) fail('File upload error: ' . $file['error']);
    if ($file['size'] > MAX_FILE_SIZE)    fail('File exceeds 10MB limit.');

    // Create uploads dir if needed
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    $originalName = basename($file['name']);
    $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName   = uniqid('att_', true) . '.' . $extension;
    $filePath     = UPLOAD_DIR . $storedName;
    $fileType     = mime_content_type($file['tmp_name']) ?: $file['type'];

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        fail('Failed to save file. Check uploads/ folder permissions.');
    }

    $uploadedBy = isset($_POST['uploadedBy']) ? (int)$_POST['uploadedBy'] : null;

    $stmt = $pdo->prepare("INSERT INTO attachments 
        (issue_id, filename, original_name, file_path, file_type, file_size, uploaded_by, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$issueId, $storedName, $originalName, $filePath, $fileType, $file['size'], $uploadedBy]);

    $newId = (int)$pdo->lastInsertId();
    $row   = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
    $row->execute([$newId]);
    ok(formatAttachment($row->fetch()), 'File uploaded successfully.');
}

// ── Delete ────────────────────────────────────────────────────

function deleteAttachment(int $id): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
    $stmt->execute([$id]);
    $att = $stmt->fetch();
    if (!$att) fail('Attachment not found.', 404);

    // Delete physical file
    if (file_exists($att['file_path'])) {
        unlink($att['file_path']);
    }

    $pdo->prepare("DELETE FROM attachments WHERE id = ?")->execute([$id]);
    ok(null, 'Attachment deleted.');
}

// ── Download ──────────────────────────────────────────────────

function downloadAttachment(int $id): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
    $stmt->execute([$id]);
    $att = $stmt->fetch();
    if (!$att || !file_exists($att['file_path'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found.']);
        exit;
    }

    // Stream the file
    header('Content-Type: ' . $att['file_type']);
    header('Content-Disposition: attachment; filename="' . addslashes($att['original_name']) . '"');
    header('Content-Length: ' . $att['file_size']);
    header('Cache-Control: no-cache');
    readfile($att['file_path']);
    exit;
}

// ── Format ────────────────────────────────────────────────────

function formatAttachment(array $r): array {
    return [
        'id'           => (int)$r['id'],
        'issueId'      => (int)$r['issue_id'],
        'originalName' => $r['original_name'],
        'storedName'   => $r['filename'],          // DB column is 'filename'
        'fileType'     => $r['file_type'] ?? '',
        'fileSize'     => (int)$r['file_size'],
        'uploadedBy'   => $r['uploaded_by'] ? (int)$r['uploaded_by'] : null,
        'createdAt'    => $r['uploaded_at'],        // DB column is 'uploaded_at'
    ];
}
