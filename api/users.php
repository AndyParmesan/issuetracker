<?php
// ============================================================
//  api/users.php  —  Users API
//  Mirrors: UsersController.cs
// ============================================================

if (!function_exists('ok')) require_once __DIR__ . '/index.php';

$method   = $_SERVER['REQUEST_METHOD'];
$segments = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));

// Find the segment after 'users'
$userId = null;
foreach ($segments as $i => $seg) {
    if ($seg === 'users' && isset($segments[$i + 1])) {
        $userId = $segments[$i + 1];
        break;
    }
}

if ($userId === null) {
    if ($method === 'GET') {
        getAllUsers();
    } else {
        fail('Method not allowed.', 405);
    }
} else {
    if ($method === 'GET') {
        getUserById((int)$userId);
    } else {
        fail('Method not allowed.', 405);
    }
}

// ── Functions ─────────────────────────────────────────────────

function getAllUsers(): void {
    $pdo  = getDB();
    $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY name ASC");
    $rows = $stmt->fetchAll();
    ok(array_map('formatUser', $rows));
}

function getUserById(int $id): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) fail('User not found.', 404);
    ok(formatUser($user));
}

function formatUser(array $r): array {
    return [
        'id'        => (int)$r['id'],
        'name'      => $r['name'],
        'email'     => $r['email'],
        'role'      => $r['role'],
        'createdAt' => $r['created_at'],
    ];
}
