<?php
// ============================================================
//  api/issues.php  —  Issues CRUD + Comments + Dashboard Stats
//  Mirrors: IssuesController.cs
// ============================================================

if (!function_exists('ok')) require_once __DIR__ . '/index.php';

// ── Entry Points ─────────────────────────────────────────────

function handleIssues(?string $id): void {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($id === null) {
        // GET /issues or POST /issues
        if ($method === 'GET')  { getAllIssues();  return; }
        if ($method === 'POST') { createIssue();   return; }
    } else {
        $id = (int)$id;
        if ($method === 'GET')    { getIssueById($id); return; }
        if ($method === 'PUT')    { updateIssue($id);  return; }
        if ($method === 'DELETE') { deleteIssue($id);  return; }
    }
    fail('Method not allowed.', 405);
}

function handleComments(int $issueId): void {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET')  { getComments($issueId);  return; }
    if ($method === 'POST') { addComment($issueId);   return; }
    fail('Method not allowed.', 405);
}

function handleDashboardStats(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed.', 405);
    getDashboardStats();
}

// ── Issues CRUD ───────────────────────────────────────────────

function getAllIssues(): void {
    $pdo      = getDB();
    $state    = $_GET['state']    ?? '';
    $priority = $_GET['priority'] ?? '';
    $search   = $_GET['search']   ?? '';

    $sql    = "SELECT i.*, 
                      ib.name AS issued_by_name,
                      at.name AS assigned_to_name
               FROM issues i
               LEFT JOIN users ib ON i.issued_by = ib.id
               LEFT JOIN users at ON i.assigned_to = at.id
               WHERE 1=1";
    $params = [];

    if ($state !== '') {
        $sql .= " AND i.state = ?";
        $params[] = $state;
    }
    if ($priority !== '') {
        $sql .= " AND i.priority = ?";
        $params[] = $priority;
    }
    if ($search !== '') {
        $sql .= " AND (i.description LIKE ? OR i.dashboard LIKE ? OR i.module LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $issues = $stmt->fetchAll();

    ok(array_map('formatIssue', $issues));
}

function getIssueById(int $id): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT i.*, 
                                  ib.name AS issued_by_name,
                                  at.name AS assigned_to_name
                           FROM issues i
                           LEFT JOIN users ib ON i.issued_by = ib.id
                           LEFT JOIN users at ON i.assigned_to = at.id
                           WHERE i.id = ?");
    $stmt->execute([$id]);
    $issue = $stmt->fetch();
    if (!$issue) fail('Issue not found.', 404);
    ok(formatIssue($issue));
}

function createIssue(): void {
    $pdo  = getDB();
    $body = bodyJson();

    $description = trim($body['description'] ?? '');
    if ($description === '') fail('Description is required.');

    $stmt = $pdo->prepare("INSERT INTO issues 
        (dashboard, module, description, state, priority, issued_by, assigned_to, date_identified, source, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Manual', NOW(), NOW())");

    $stmt->execute([
        $body['dashboard']      ?? null,
        $body['module']         ?? null,
        $description,
        $body['state']          ?? 'New',
        $body['priority']       ?? 'Medium',
        $body['issuedBy']       ?? null,
        $body['assignedTo']     ?? null,
        $body['dateIdentified'] ?? date('Y-m-d'),
    ]);

    $newId = (int)$pdo->lastInsertId();
    getIssueById($newId);
}

function updateIssue(int $id): void {
    $pdo  = getDB();
    $body = bodyJson();

    // Check exists
    $check = $pdo->prepare("SELECT id FROM issues WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) fail('Issue not found.', 404);

    $fields = [];
    $params = [];

    $allowed = ['dashboard', 'module', 'description', 'state', 'priority'];
    foreach ($allowed as $field) {
        $camel = lcfirst(str_replace('_', '', ucwords($field, '_')));
        if (isset($body[$camel])) {
            $fields[] = "$field = ?";
            $params[]  = $body[$camel];
        }
    }
    // assigned_to special case (camelCase: assignedTo)
    if (array_key_exists('assignedTo', $body)) {
        $fields[]  = "assigned_to = ?";
        $params[]  = $body['assignedTo'] ?: null;
    }

    if (empty($fields)) fail('Nothing to update.');

    $fields[]  = "updated_at = NOW()";
    $params[]  = $id;

    $pdo->prepare("UPDATE issues SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    getIssueById($id);
}

function deleteIssue(int $id): void {
    $pdo   = getDB();
    $check = $pdo->prepare("SELECT id FROM issues WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) fail('Issue not found.', 404);

    $pdo->prepare("DELETE FROM issues WHERE id = ?")->execute([$id]);
    ok(null, 'Issue deleted successfully.');
}

// ── Comments ─────────────────────────────────────────────────

function getComments(int $issueId): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT c.*, u.name AS user_name
                           FROM comments c
                           LEFT JOIN users u ON c.user_id = u.id
                           WHERE c.issue_id = ?
                           ORDER BY c.created_at ASC");
    $stmt->execute([$issueId]);
    $rows = $stmt->fetchAll();
    ok(array_map(fn($r) => [
        'id'          => (int)$r['id'],
        'issueId'     => (int)$r['issue_id'],
        'userId'      => (int)$r['user_id'],
        'commentText' => $r['comment'],        // DB column is 'comment'
        'createdAt'   => $r['created_at'],
        'userName'    => $r['user_name'],
    ], $rows));
}

function addComment(int $issueId): void {
    $pdo  = getDB();
    $body = bodyJson();

    $text = trim($body['commentText'] ?? '');
    if ($text === '') fail('Comment text is required.');

    $userId = (int)($body['userId'] ?? 0);

    $pdo->prepare("INSERT INTO comments (issue_id, user_id, comment, created_at)
                   VALUES (?, ?, ?, NOW())")->execute([$issueId, $userId ?: null, $text]);

    getComments($issueId);
}

// ── Dashboard Stats ───────────────────────────────────────────

function getDashboardStats(): void {
    $pdo = getDB();

    // Counts
    $counts = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(state = 'New') AS new_count,
        SUM(state = 'Bug') AS bug_count,
        SUM(state = 'Open') AS open_count,
        SUM(state = 'In Progress') AS in_progress_count,
        SUM(state = 'Resolved') AS resolved_count
        FROM issues")->fetch();

    // By Priority
    $byPriority = $pdo->query("SELECT priority, COUNT(*) AS count
                                FROM issues
                                GROUP BY priority
                                ORDER BY FIELD(priority,'Critical','High','Medium','Low')")->fetchAll();

    // By Dashboard
    $byDashboard = $pdo->query("SELECT COALESCE(dashboard,'Unknown') AS dashboard, COUNT(*) AS count
                                 FROM issues
                                 GROUP BY dashboard
                                 ORDER BY count DESC
                                 LIMIT 8")->fetchAll();

    ok([
        'totalIssues'     => (int)$counts['total'],
        'newCount'        => (int)$counts['new_count'],
        'bugCount'        => (int)$counts['bug_count'],
        'openCount'       => (int)$counts['open_count'],
        'inProgressCount' => (int)$counts['in_progress_count'],
        'resolvedCount'   => (int)$counts['resolved_count'],
        'byPriority'      => array_map(fn($r) => [
            'priority' => $r['priority'],
            'count'    => (int)$r['count'],
        ], $byPriority),
        'byDashboard'     => array_map(fn($r) => [
            'dashboard' => $r['dashboard'],
            'count'     => (int)$r['count'],
        ], $byDashboard),
    ]);
}

// ── Format Helper ─────────────────────────────────────────────

function formatIssue(array $r): array {
    return [
        'id'             => (int)$r['id'],
        'dashboard'      => $r['dashboard'],
        'module'         => $r['module'],
        'description'    => $r['description'],
        'state'          => $r['state'],
        'priority'       => $r['priority'],
        'issuedBy'       => $r['issued_by']   ? (int)$r['issued_by']   : null,
        'assignedTo'     => $r['assigned_to'] ? (int)$r['assigned_to'] : null,
        'issuedByName'   => $r['issued_by_name']   ?? null,
        'assignedToName' => $r['assigned_to_name'] ?? null,
        'dateIdentified' => $r['date_identified'],
        'source'         => $r['source'] ?? 'Manual',
        'createdAt'      => $r['created_at'],
        'updatedAt'      => $r['updated_at'],
    ];
}
