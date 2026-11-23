-- FixTrack Campus - Database schema + minimal sample data
-- Run: mysql -u root -p fixtrack_db < database.sql

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS repair_verifications;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS status_history;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS report_images;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS facilities;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Schema
-- --------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'technician') NOT NULL DEFAULT 'student',
    phone VARCHAR(30) NULL,
    identifier VARCHAR(50) NULL,
    specialization VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    building_code VARCHAR(30) NOT NULL UNIQUE,
    campus_zone VARCHAR(100) NOT NULL,
    description TEXT NULL,
    total_floors INT DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'wrench',
    default_priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    default_sla_hours INT DEFAULT 48,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_code VARCHAR(30) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    category_id INT NOT NULL,
    facility_id INT NOT NULL,
    room_number VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    status ENUM('REPORTED', 'UNDER REVIEW', 'APPROVED', 'ASSIGNED', 'IN PROGRESS',
                'RESOLVED', 'VERIFIED', 'CLOSED', 'REJECTED') NOT NULL DEFAULT 'REPORTED',
    deadline DATETIME NULL,
    rejection_reason TEXT NULL,
    resolved_at DATETIME NULL,
    verified_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_report_facility FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE RESTRICT,
    INDEX idx_report_tracking (tracking_code),
    INDEX idx_report_status (status),
    INDEX idx_report_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    image_type ENUM('evidence', 'completion') NOT NULL DEFAULT 'evidence',
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    caption VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_image_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_image_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    technician_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assignment_notes TEXT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    status ENUM('pending', 'in_progress', 'completed', 'reassigned') NOT NULL DEFAULT 'pending',
    CONSTRAINT fk_assign_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_assign_tech FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_assign_admin FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assign_tech (technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    changed_by INT NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_history_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_id INT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'status_update',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repair_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL UNIQUE,
    student_id INT NOT NULL,
    is_satisfied TINYINT(1) NOT NULL DEFAULT 1,
    rating TINYINT NOT NULL DEFAULT 5,
    feedback_notes TEXT NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_verify_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_verify_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Minimal sample data (3 users, 2 buildings, 3 reports)
-- Passwords: Admin@123 / Tech@123 / Student@123
-- --------------------------------------------------------
INSERT INTO users (id, full_name, email, password_hash, role, phone, identifier, specialization) VALUES
(1, 'Admin User', 'admin@fixtrack.campus', '$2y$10$cFZ/lq6v1OW7YODjcQgSG.8TB.19VPbYqmpLUbvDReuFxMrTCjuOu', 'admin', NULL, 'ADM-001', NULL),
(2, 'Sam Tech', 'tech.electrical@fixtrack.campus', '$2y$10$0gfPWphPGO/zm/R9UDxHfeR8tFzTbvFFQMhkI0cb0KXkY127lDXpi', 'technician', NULL, 'TECH-001', 'Electrical'),
(3, 'Alice Student', 'alice.student@fixtrack.campus', '$2y$10$CZEcPD/kZQZsg8IZYPPjPeuxngtU/LF/RpuNNdIwP.0tzfoO9ul9y', 'student', NULL, 'STU-2024-1049', NULL);

INSERT INTO facilities (id, name, building_code, campus_zone, description, total_floors) VALUES
(1, 'Engineering Block', 'ENG-B1', 'Engineering Quad', 'Lecture halls and labs', 3),
(2, 'Main Library', 'LIB-MAIN', 'Central Campus', 'Reading areas and study pods', 2);

INSERT INTO categories (id, name, slug, icon, default_priority, default_sla_hours) VALUES
(1, 'Electrical problems', 'electrical', 'bolt', 'high', 24),
(2, 'Internet and network problems', 'internet-network', 'wifi', 'medium', 48),
(3, 'Laboratory equipment failures', 'lab-equipment', 'flask', 'high', 48),
(4, 'Classroom equipment failures', 'classroom-equipment', 'presentation', 'medium', 36),
(5, 'Plumbing and water problems', 'plumbing-water', 'droplet', 'high', 24),
(6, 'Furniture damage', 'furniture', 'armchair', 'low', 72),
(7, 'Lighting problems', 'lighting', 'sun', 'medium', 48),
(8, 'General infrastructure problems', 'general-infrastructure', 'building', 'low', 96);

INSERT INTO reports (id, tracking_code, student_id, category_id, facility_id, room_number, title, description, priority, status, deadline, resolved_at, created_at) VALUES
(1, 'FTC-2026-10001', 3, 1, 1, 'Lab E-204', 'Burned wall socket', 'The wall socket near workbench 4 sparks when a test bench is powered on.', 'critical', 'IN PROGRESS', DATE_ADD(NOW(), INTERVAL 12 HOUR), NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'FTC-2026-10002', 3, 7, 2, '2nd Floor Study Pod', 'Flickering ceiling light', 'The light above carrels 14-20 flickers continuously and buzzes.', 'medium', 'RESOLVED', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'FTC-2026-10003', 3, 5, 1, 'Ground Floor Restroom', 'Leaking tap', 'The left washbasin tap will not shut off completely and water pools on the floor.', 'high', 'REPORTED', NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO assignments (id, report_id, technician_id, assigned_by, assignment_notes, assigned_at, completed_at, status) VALUES
(1, 1, 2, 1, 'Replace the damaged socket and test the circuit.', DATE_SUB(NOW(), INTERVAL 20 HOUR), NULL, 'in_progress'),
(2, 2, 2, 1, 'Replace the faulty LED driver.', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 6 HOUR), 'completed');

INSERT INTO status_history (report_id, changed_by, old_status, new_status, notes, created_at) VALUES
(1, 3, NULL, 'REPORTED', 'Issue reported.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 1, 'REPORTED', 'APPROVED', 'Confirmed electrical hazard.', DATE_SUB(NOW(), INTERVAL 22 HOUR)),
(1, 1, 'APPROVED', 'ASSIGNED', 'Assigned to Sam Tech.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(1, 2, 'ASSIGNED', 'IN PROGRESS', 'Power isolated at the distribution board.', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(2, 3, NULL, 'REPORTED', 'Issue reported.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, 'REPORTED', 'ASSIGNED', 'Assigned to Sam Tech.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 2, 'ASSIGNED', 'RESOLVED', 'LED driver replaced and tested.', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(3, 3, NULL, 'REPORTED', 'Issue reported.', DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO notifications (user_id, report_id, title, message, type, is_read, created_at) VALUES
(3, 1, 'Work in progress', 'Sam Tech has started work on FTC-2026-10001.', 'status_change', 0, DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(3, 2, 'Repair completed - verification needed', 'Please verify the repair for FTC-2026-10002.', 'verification_needed', 0, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(1, 3, 'New report', 'Alice Student submitted FTC-2026-10003 (leaking tap).', 'new_report', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR));
