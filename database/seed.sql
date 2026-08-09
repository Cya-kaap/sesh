-- =====================================================
-- SESH / SRFMS - Seed Data
-- =====================================================
--
-- Matches the corrected schema.sql in this same folder. Written to
-- be idempotent (INSERT ... ON DUPLICATE KEY UPDATE / INSERT IGNORE)
-- so re-running it against a database that already has this data
-- won't create duplicate rows or error out.
--
-- ⚠ SECURITY: change these default passwords immediately after your
-- first real login on any environment other than local development.
-- =====================================================

USE sesh;

-- -----------------------------------------------------
-- Programmes
-- -----------------------------------------------------
INSERT INTO programmes (name) VALUES
    ('BCA'), ('BBA'), ('BBM'), ('BBS'), ('BIM'), ('BSc CSIT')
ON DUPLICATE KEY UPDATE name = name;

-- -----------------------------------------------------
-- Semesters — 8 per programme
-- -----------------------------------------------------
INSERT INTO semesters (programme_id, number)
SELECT p.id, n.number
FROM programmes p
CROSS JOIN (
    SELECT 1 AS number UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
) n
WHERE NOT EXISTS (
    SELECT 1 FROM semesters sm
    WHERE sm.programme_id = p.id AND sm.number = n.number
);

-- -----------------------------------------------------
-- Default accounts
-- Password for all three: 123456   (CHANGE THIS after first login)
-- -----------------------------------------------------
INSERT INTO users (full_name, email, password_hash, role, department, status) VALUES
    ('Project Admin',    'admin@sesh.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin',       'BCA', 'Active'),
    ('Exam Coordinator', 'coordinator@sesh.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Coordinator', 'BCA', 'Active'),
    ('Faculty Member',   'faculty@sesh.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Faculty',     'BCA', 'Active')
ON DUPLICATE KEY UPDATE full_name = full_name;

-- -----------------------------------------------------
-- Sample Rooms
-- NOTE: rooms.name has no UNIQUE constraint at the database level
-- (duplicates are only prevented app-side, in admin/rooms/create.php)
-- so this uses WHERE NOT EXISTS rather than ON DUPLICATE KEY UPDATE
-- to stay idempotent.
-- -----------------------------------------------------
INSERT INTO rooms (name, type, capacity, has_projector, has_whiteboard, has_ac, status)
SELECT * FROM (SELECT
    'Lab-101' AS name, 'Laboratory' AS type, 40 AS capacity, 1 AS has_projector, 1 AS has_whiteboard, 1 AS has_ac, 'Available' AS status
    UNION ALL SELECT 'Lab-102',       'Laboratory',      35, 1, 1, 0, 'Available'
    UNION ALL SELECT 'Seminar Hall',  'Seminar Hall',    120, 1, 0, 1, 'Available'
    UNION ALL SELECT 'Classroom-201', 'Classroom',       60, 0, 1, 1, 'Available'
    UNION ALL SELECT 'Exam Hall 1',   'Conference Room', 80, 0, 1, 0, 'Available'
) AS candidate
WHERE NOT EXISTS (
    SELECT 1 FROM rooms r WHERE r.name = candidate.name
);