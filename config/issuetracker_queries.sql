-- ============================================================
--  issuetracker_queries.sql
--  Full reference query set for all active tables
--  Excludes: employee_names (confirmed dead weight)
-- ============================================================

USE issuetracker;


-- ============================================================
-- 1. ISSUES
-- ============================================================

-- All issues with related names
SELECT
    i.id,
    i.title,
    i.dashboard,
    p.name              AS particular,
    i.module,
    i.description,
    i.state,
    i.status,
    i.priority,
    i.story_points,
    i.area_path,
    i.iteration_path,
    i.acceptance_criteria,
    i.source,
    i.date_identified,
    i.created_at,
    i.updated_at,
    u1.name             AS issued_by,
    u2.name             AS assigned_to
FROM issues i
LEFT JOIN users       u1 ON i.issued_by      = u1.id
LEFT JOIN users       u2 ON i.assigned_to    = u2.id
LEFT JOIN particulars p  ON i.particular_id  = p.particular_id
ORDER BY i.created_at DESC;

-- Issues by particular
SELECT
    p.name              AS particular,
    COUNT(*)            AS total_issues,
    SUM(i.state IN ('New','Bug','Open'))         AS open,
    SUM(i.status = 'In Progress')               AS in_progress,
    SUM(i.status = 'Resolved')                  AS resolved
FROM issues i
LEFT JOIN particulars p ON i.particular_id = p.particular_id
GROUP BY p.particular_id, p.name
ORDER BY total_issues DESC;

-- Issues by assigned developer
SELECT
    u.name              AS developer,
    COUNT(*)            AS total_assigned,
    SUM(i.status = 'In Progress')   AS in_progress,
    SUM(i.status = 'Fixed')         AS fixed,
    SUM(i.status = 'Resolved')      AS resolved
FROM issues i
JOIN users u ON i.assigned_to = u.id
GROUP BY u.id, u.name
ORDER BY total_assigned DESC;

-- Issues by priority
SELECT
    priority,
    COUNT(*) AS count
FROM issues
GROUP BY priority
ORDER BY FIELD(priority, '1-Urgent','2-Critical','3-High','4-Medium','5-Low');

-- Issues with no particular assigned (orphaned)
SELECT id, title, description, dashboard, created_at
FROM issues
WHERE particular_id IS NULL
ORDER BY created_at DESC;

-- Update issue state/status
UPDATE issues
SET state  = 'In Progress',
    status = 'In Progress',
    updated_at = NOW()
WHERE id = 1;  -- replace with target id

-- Delete issue (cascades to comments and attachments via FK)
DELETE FROM issues WHERE id = 1;  -- replace with target id


-- ============================================================
-- 2. USERS
-- ============================================================

-- All users with particular name
SELECT
    u.id,
    u.name,
    u.username,
    u.email,
    u.role,
    p.name  AS particular,
    u.isActive,
    u.created_at
FROM users u
LEFT JOIN particulars p ON u.particular_id = p.particular_id
ORDER BY u.role, u.name;

-- Active users only
SELECT u.id, u.name, u.username, u.role, p.name AS particular
FROM users u
LEFT JOIN particulars p ON u.particular_id = p.particular_id
WHERE u.isActive = 1
ORDER BY u.role, u.name;

-- Users per role count
SELECT role, COUNT(*) AS count
FROM users
GROUP BY role
ORDER BY count DESC;

-- Reset a user password (MD5 of 'admin')
UPDATE users
SET password = MD5('admin'), updated_at = NOW()
WHERE username = 'jsmith';  -- replace with target username

-- Deactivate a user
UPDATE users SET isActive = 0, updated_at = NOW() WHERE id = 5;

-- Reactivate a user
UPDATE users SET isActive = 1, updated_at = NOW() WHERE id = 5;


-- ============================================================
-- 3. PARTICULARS
-- ============================================================

-- All particulars
SELECT particular_id, name, isActive, created_at
FROM particulars
ORDER BY particular_id;

-- Active particulars only
SELECT particular_id, name
FROM particulars
WHERE isActive = 1
ORDER BY name;

-- Issues count per particular (including inactive)
SELECT
    p.particular_id,
    p.name,
    p.isActive,
    COUNT(i.id) AS issue_count
FROM particulars p
LEFT JOIN issues i ON i.particular_id = p.particular_id
GROUP BY p.particular_id, p.name, p.isActive
ORDER BY issue_count DESC;

-- Users per particular
SELECT
    p.name              AS particular,
    COUNT(u.id)         AS user_count,
    GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') AS members
FROM particulars p
LEFT JOIN users u ON u.particular_id = p.particular_id AND u.isActive = 1
GROUP BY p.particular_id, p.name
ORDER BY p.particular_id;

-- Add new particular
INSERT INTO particulars (name, isActive) VALUES ('NewDept', 1);

-- Deactivate a particular
UPDATE particulars SET isActive = 0 WHERE particular_id = 3;


-- ============================================================
-- 4. COMMENTS
-- ============================================================

-- All comments for a specific issue
SELECT
    c.id,
    c.author,
    c.comment,
    c.created_at
FROM comments c
WHERE c.issue_id = 1  -- replace with target issue id
ORDER BY c.created_at ASC;

-- Comment count per issue (top 10 most discussed)
SELECT
    i.id            AS issue_id,
    COALESCE(i.title, LEFT(i.description, 60)) AS issue,
    COUNT(c.id)     AS comment_count
FROM issues i
LEFT JOIN comments c ON c.issue_id = i.id
GROUP BY i.id
ORDER BY comment_count DESC
LIMIT 10;

-- Delete a comment
DELETE FROM comments WHERE id = 5;  -- replace with target comment id


-- ============================================================
-- 5. ATTACHMENTS
-- ============================================================

-- All attachments for a specific issue
SELECT
    a.id,
    a.original_name,
    a.file_type,
    a.file_size,
    a.file_path,
    u.name  AS uploaded_by,
    a.uploaded_at
FROM attachments a
LEFT JOIN users u ON a.uploaded_by = u.id
WHERE a.issue_id = 1  -- replace with target issue id
ORDER BY a.uploaded_at DESC;

-- Attachment count and size per issue
SELECT
    i.id            AS issue_id,
    COALESCE(i.title, LEFT(i.description,60)) AS issue,
    COUNT(a.id)     AS attachment_count,
    ROUND(SUM(a.file_size)/1024/1024, 2) AS total_mb
FROM issues i
LEFT JOIN attachments a ON a.issue_id = i.id
GROUP BY i.id
HAVING attachment_count > 0
ORDER BY total_mb DESC;

-- Delete an attachment record (file on disk must be removed separately)
DELETE FROM attachments WHERE id = 3;  -- replace with target attachment id


-- ============================================================
-- 6. REPORTS
-- ============================================================

-- All reports
SELECT
    r.id,
    u.name          AS generated_by,
    r.date_range,
    r.status_filter,
    r.total_issues,
    r.new_count,
    r.bug_count,
    r.open_count,
    r.in_progress_count,
    r.resolved_count,
    r.generated_at
FROM reports r
LEFT JOIN users u ON r.generated_by = u.id
ORDER BY r.generated_at DESC;

-- Reports generated this month
SELECT r.*, u.name AS generated_by
FROM reports r
LEFT JOIN users u ON r.generated_by = u.id
WHERE MONTH(r.generated_at) = MONTH(NOW())
  AND YEAR(r.generated_at)  = YEAR(NOW())
ORDER BY r.generated_at DESC;

-- Delete old reports (older than 90 days)
DELETE FROM reports
WHERE generated_at < DATE_SUB(NOW(), INTERVAL 90 DAY);


-- ============================================================
-- 7. ISSUE_IMPORTS
-- ============================================================

-- All import logs
SELECT
    ii.id,
    u.name          AS imported_by,
    ii.filename,
    ii.records_count,
    ii.imported_at
FROM issue_imports ii
LEFT JOIN users u ON ii.imported_by = u.id
ORDER BY ii.imported_at DESC;

-- Total records imported per user
SELECT
    u.name          AS user,
    COUNT(ii.id)    AS import_runs,
    SUM(ii.records_count) AS total_records_imported
FROM issue_imports ii
JOIN users u ON ii.imported_by = u.id
GROUP BY u.id, u.name
ORDER BY total_records_imported DESC;


-- ============================================================
-- 8. v_master_users (VIEW — read only)
-- ============================================================

-- All users via view (includes particular_name, no join needed)
SELECT id, name, email, role, isActive, particular_id, particular_name
FROM v_master_users
ORDER BY role, name;

-- Active users via view
SELECT id, name, role, particular_name
FROM v_master_users
WHERE isActive = 1
ORDER BY role, name;


-- ============================================================
-- DEAD WEIGHT ASSESSMENT
-- ============================================================

-- employee_names: confirmed unused — safe to drop
-- DROP TABLE employee_names;

-- Verify nothing references it before dropping:
SELECT COUNT(*) AS row_count FROM employee_names;
