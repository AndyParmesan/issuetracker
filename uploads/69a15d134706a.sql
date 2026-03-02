-- ============================================================
--  IssueTracker — MySQL Database Schema
--  Run this in phpMyAdmin or MySQL CLI:
--  mysql -u aj -p issuetracker < schema.sql
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
CREATE TABLE IF NOT EXISTS issues (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    dashboard        VARCHAR(120)  NULL,
    module           VARCHAR(120)  NULL,
    description      TEXT          NOT NULL,
    state            ENUM('New','Bug','Open','In Progress','Resolved') NOT NULL DEFAULT 'New',
    priority         ENUM('Low','Medium','High','Critical')            NOT NULL DEFAULT 'Medium',
    issued_by        INT NULL,
    assigned_to      INT NULL,
    date_identified  DATE NOT NULL,
    source           VARCHAR(50)  NOT NULL DEFAULT 'Manual',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (issued_by)   REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Comments ──────────────────────────────────────────────────
-- Real DB column is 'comment' (not 'comment_text')
CREATE TABLE IF NOT EXISTS comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    issue_id   INT NOT NULL,
    user_id    INT NOT NULL,
    comment    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Attachments ───────────────────────────────────────────────
-- Real DB columns: filename (stored name), original_name, uploaded_at
CREATE TABLE IF NOT EXISTS attachments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    issue_id      INT          NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path     TEXT         NOT NULL,
    file_size     BIGINT,
    file_type     VARCHAR(100),
    uploaded_by   INT,
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

-- ── Seed Users (sample) ───────────────────────────────────────
INSERT IGNORE INTO users (name, email, role) VALUES
  ('Admin User',  'admin@issuetracker.local',  'admin'),
  ('Staff One',   'staff1@issuetracker.local', 'staff'),
  ('Staff Two',   'staff2@issuetracker.local', 'staff');
