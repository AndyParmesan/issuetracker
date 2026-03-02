-- ============================================================
--  fix_users.sql  (updated)
--  Run in phpMyAdmin → issuetracker DB → SQL tab
--
--  Developers (Assigned To): Stephen Dumili, Rocky
--  Reporters  (Issued By):   Dianne, Kath, Jemson, Josh
-- ============================================================

USE issuetracker;

-- Step 1: Expand role ENUM to support developer/reporter
ALTER TABLE users MODIFY COLUMN role 
  ENUM('admin','staff','viewer','developer','reporter') NOT NULL DEFAULT 'staff';

-- Step 2: Remove all existing users
DELETE FROM users;

-- Step 3: Reset auto-increment
ALTER TABLE users AUTO_INCREMENT = 1;

-- Step 4: Insert correct users with proper roles
INSERT INTO users (name, email, role) VALUES
  ('Stephen Dumili', 'stephen@issuetracker.local', 'developer'),
  ('Rocky',          'rocky@issuetracker.local',   'developer'),
  ('Dianne',         'dianne@issuetracker.local',  'reporter'),
  ('Kath',           'kath@issuetracker.local',    'reporter'),
  ('Jemson',         'jemson@issuetracker.local',  'reporter'),
  ('Josh',           'josh@issuetracker.local',    'reporter');

-- Step 5: Verify
SELECT id, name, role FROM users ORDER BY role, name;
