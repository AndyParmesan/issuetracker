<?php
// ============================================================
//  api/index.php  —  Front Controller / Router
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../configs/database.php';

// ── Helpers ──────────────────────────────────────────────────

function respond(bool $success, $data = null, string $message = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message ?: null,
        'data'    => $data,
    ]);
    exit;
}

function ok($data = null, string $msg = '', int $code = 200): void {
    respond(true, $data, $msg, $code);
}

function fail(string $msg, int $code = 400): void {
    respond(false, null, $msg, $code);
}

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

// ── Routing ───────────────────────────────────────────────────
// Parse URL: /api/issues, /api/issues/5, /api/issues/5/comments, etc.

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim(preg_replace('#/+#', '/', $uri), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Strip base path (adjust if your XAMPP path is different)
$base = '/issuetracker/api';
if (strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}
$uri = ltrim($uri, '/');

$segments = $uri ? explode('/', $uri) : [];

// Route: /users
if ($segments[0] === 'users') {
    require_once __DIR__ . '/users.php';
    exit;
}

// Route: /import/csv
if ($segments[0] === 'import') {
    require_once __DIR__ . '/import.php';
    exit;
}

// Route: /reports
if ($segments[0] === 'reports') {
    require_once __DIR__ . '/reports.php';
    exit;
}

// Route: /issues/dashboard/stats
if ($segments[0] === 'issues' && ($segments[1] ?? '') === 'dashboard' && ($segments[2] ?? '') === 'stats') {
    require_once __DIR__ . '/issues.php';
    handleDashboardStats();
    exit;
}

// Route: /issues/{id}/comments
if ($segments[0] === 'issues' && isset($segments[1]) && ($segments[2] ?? '') === 'comments') {
    require_once __DIR__ . '/issues.php';
    handleComments((int)$segments[1]);
    exit;
}

// Route: /issues/{id}/attachments/{attId}/download
if ($segments[0] === 'issues' && isset($segments[1]) && ($segments[2] ?? '') === 'attachments' && isset($segments[3]) && ($segments[4] ?? '') === 'download') {
    require_once __DIR__ . '/attachments.php';
    handleDownload((int)$segments[1], (int)$segments[3]);
    exit;
}

// Route: /issues/{id}/attachments/{attId}
if ($segments[0] === 'issues' && isset($segments[1]) && ($segments[2] ?? '') === 'attachments' && isset($segments[3])) {
    require_once __DIR__ . '/attachments.php';
    handleAttachment((int)$segments[1], (int)$segments[3]);
    exit;
}

// Route: /issues/{id}/attachments
if ($segments[0] === 'issues' && isset($segments[1]) && ($segments[2] ?? '') === 'attachments') {
    require_once __DIR__ . '/attachments.php';
    handleAttachments((int)$segments[1]);
    exit;
}

// Route: /issues or /issues/{id}
if ($segments[0] === 'issues') {
    require_once __DIR__ . '/issues.php';
    handleIssues($segments[1] ?? null);
    exit;
}

fail('Endpoint not found.', 404);
