-- ============================================================
--  IssueTracker — MySQL Database Schema (v2 — PDF Aligned)
--  Updates:
--   - issues.state ENUM expanded to match PDF User Story states
--   - issues.priority updated to include Urgent (numeric labels)
--   - New columns: title, story_points, iteration_path, area_path, acceptance_criteria
--   - All existing tables preserved and compatible
-- ============================================================

CREATE DATABASE IF NOT EXISTS issuetracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE issuetracker;

-- ── Users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(180) NOT NULL UNIQUE,
    role       ENUM('admin','staff','viewer') NOT NULL DEFAULT 'staff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Issues ────────────────────────────────────────────────────
-- State values aligned with PDF:
--   Draft → For Review → Approved → In Development → For Testing
--   → QA Failed → For UAT → Ready for Deployment → Deployed → Closed
-- Legacy states (New, Bug, Open, In Progress, Resolved) kept for compatibility
CREATE TABLE IF NOT EXISTS issues (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(255)  NULL,                          -- Short story title (PDF: Header > Title)
    dashboard           VARCHAR(120)  NULL,
    module              VARCHAR(120)  NULL,
    description         TEXT          NOT NULL,
    state               ENUM(
                          'Draft',
                          'For Review',
                          'Approved',
                          'In Development',
                          'For Testing',
                          'QA Failed',
                          'For UAT',
                          'Ready for Deployment',
                          'Deployed',
                          'Closed',
                          'New',
                          'Bug',
                          'Open',
                          'In Progress',
                          'Resolved'
                        ) NOT NULL DEFAULT 'Draft',
    priority            ENUM(
                          '1-Urgent',
                          '2-Critical',
                          '3-High',
                          '4-Medium',
                          '5-Low'
                        ) NOT NULL DEFAULT '4-Medium',
    story_points        ENUM('1','2','3','5','8','13') NULL,          -- PDF: Right Panel > Story Points
    area_path           VARCHAR(255)  NULL,                          -- PDF: Header > Area Path
    iteration_path      VARCHAR(255)  NULL,                          -- PDF: Header > Iteration Path (Sprint)
    acceptance_criteria TEXT          NULL,                          -- PDF: Development Section > Acceptance Criteria
    issued_by           INT NULL,
    assigned_to         INT NULL,
    date_identified     DATE NOT NULL DEFAULT (CURRENT_DATE),
    source              VARCHAR(50)  NOT NULL DEFAULT 'Manual',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (issued_by)   REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Comments ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    issue_id   INT NOT NULL,
    user_id    INT NULL,
    author     VARCHAR(120) NULL,   -- fallback display name when user_id not available
    comment    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Attachments ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attachments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    issue_id      INT          NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path     TEXT         NOT NULL,
    file_size     BIGINT,
    file_type     VARCHAR(100),
    uploaded_by   INT NULL,
    uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id)    REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Reports ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reports (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    generated_by       INT NULL,
    date_range         VARCHAR(50)  NULL,
    status_filter      VARCHAR(50)  NOT NULL DEFAULT 'All Statuses',
    total_issues       INT NOT NULL DEFAULT 0,
    new_count          INT NOT NULL DEFAULT 0,
    bug_count          INT NOT NULL DEFAULT 0,
    open_count         INT NOT NULL DEFAULT 0,
    in_progress_count  INT NOT NULL DEFAULT 0,
    resolved_count     INT NOT NULL DEFAULT 0,
    generated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── CSV Import Log ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS issue_imports (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    filename      VARCHAR(255) NULL,
    imported_by   INT NULL,
    records_count INT NOT NULL DEFAULT 0,
    imported_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Migration: Add new columns to existing issues table ───────
-- Run these ALTER statements if you already have an existing DB
-- and don't want to drop and recreate:

-- ALTER TABLE issues ADD COLUMN title VARCHAR(255) NULL AFTER id;
-- ALTER TABLE issues ADD COLUMN story_points ENUM('1','2','3','5','8','13') NULL AFTER priority;
-- ALTER TABLE issues ADD COLUMN area_path VARCHAR(255) NULL AFTER story_points;
-- ALTER TABLE issues ADD COLUMN iteration_path VARCHAR(255) NULL AFTER area_path;
-- ALTER TABLE issues ADD COLUMN acceptance_criteria TEXT NULL AFTER iteration_path;
-- ALTER TABLE issues MODIFY COLUMN state ENUM('Draft','For Review','Approved','In Development','For Testing','QA Failed','For UAT','Ready for Deployment','Deployed','Closed','New','Bug','Open','In Progress','Resolved') NOT NULL DEFAULT 'Draft';
-- ALTER TABLE issues MODIFY COLUMN priority ENUM('1-Urgent','2-Critical','3-High','4-Medium','5-Low') NOT NULL DEFAULT '4-Medium';
-- ALTER TABLE comments ADD COLUMN author VARCHAR(120) NULL AFTER user_id;

-- ── Seed Users ────────────────────────────────────────────────
INSERT IGNORE INTO users (name, email, role) VALUES
  ('Admin User',  'admin@issuetracker.local',  'admin'),
  ('Staff One',   'staff1@issuetracker.local', 'staff'),
  ('Staff Two',   'staff2@issuetracker.local', 'staff');
