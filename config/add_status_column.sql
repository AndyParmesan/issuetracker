-- ============================================================
--  add_status_column.sql
--  Run in phpMyAdmin → issuetracker DB → SQL tab
--  Adds a separate 'status' column to track dev progress
-- ============================================================

USE issuetracker;

ALTER TABLE issues
  ADD COLUMN IF NOT EXISTS status 
    ENUM('In Progress','Fixed','Resolved') 
    NULL DEFAULT NULL
  AFTER state;

-- All existing issues that are "In Progress" or "Resolved" 
-- get migrated to the new status column
UPDATE issues SET status = 'In Progress' WHERE state = 'In Progress' AND status IS NULL;
UPDATE issues SET status = 'Resolved'    WHERE state = 'Resolved'    AND status IS NULL;

-- Verify
SELECT id, state, status FROM issues LIMIT 10;
