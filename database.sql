-- FixTrack Campus Database Initialization
-- Campus Facilities and Maintenance Management System

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
-- Table: users
-- --------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'technician') NOT NULL DEFAULT 'student',
    phone VARCHAR(30) NULL,
    identifier VARCHAR(50) NULL, -- Student Reg No (e.g. STU-2024-1049) or Staff/Tech ID (TECH-001)
    specialization VARCHAR(100) NULL, -- For technicians
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role),
    INDEX idx_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: facilities
-- --------------------------------------------------------
CREATE TABLE facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    building_code VARCHAR(30) NOT NULL UNIQUE,
    campus_zone VARCHAR(100) NOT NULL,
    description TEXT NULL,
    total_floors INT DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_building_code (building_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Table: reports
-- --------------------------------------------------------
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
    status ENUM('REPORTED', 'UNDER REVIEW', 'APPROVED', 'ASSIGNED', 'IN PROGRESS', 'RESOLVED', 'VERIFIED', 'CLOSED', 'REJECTED') NOT NULL DEFAULT 'REPORTED',
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
    INDEX idx_report_priority (priority),
    INDEX idx_report_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: report_images
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Table: assignments
-- --------------------------------------------------------
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
    INDEX idx_assign_tech (technician_id),
    INDEX idx_assign_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: status_history
-- --------------------------------------------------------
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
    INDEX idx_history_report (report_id),
    INDEX idx_history_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Table: repair_verifications
-- --------------------------------------------------------
CREATE TABLE repair_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL UNIQUE,
    student_id INT NOT NULL,
    is_satisfied TINYINT(1) NOT NULL DEFAULT 1,
    rating TINYINT NOT NULL DEFAULT 5, -- 1 to 5 stars
    feedback_notes TEXT NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_verify_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_verify_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================
-- SEED DATA
-- ========================================================

-- Users (Password: Admin@123, Tech@123, Student@123)
-- Admin hash: $2y$10$cFZ/lq6v1OW7YODjcQgSG.8TB.19VPbYqmpLUbvDReuFxMrTCjuOu
-- Tech hash:  $2y$10$0gfPWphPGO/zm/R9UDxHfeR8tFzTbvFFQMhkI0cb0KXkY127lDXpi
-- Student hash: $2y$10$CZEcPD/kZQZsg8IZYPPjPeuxngtU/LF/RpuNNdIwP.0tzfoO9ul9y

INSERT INTO users (id, full_name, email, password_hash, role, phone, identifier, specialization) VALUES
(1, 'Director Marcus Vance', 'admin@fixtrack.campus', '$2y$10$cFZ/lq6v1OW7YODjcQgSG.8TB.19VPbYqmpLUbvDReuFxMrTCjuOu', 'admin', '+254 700 112 233', 'ADM-001', 'Facilities Director'),
(2, 'Samuel Sparks', 'tech.electrical@fixtrack.campus', '$2y$10$0gfPWphPGO/zm/R9UDxHfeR8tFzTbvFFQMhkI0cb0KXkY127lDXpi', 'technician', '+254 711 223 344', 'TECH-001', 'Electrical & Power Systems'),
(3, 'Peter Pipes', 'tech.plumbing@fixtrack.campus', '$2y$10$0gfPWphPGO/zm/R9UDxHfeR8tFzTbvFFQMhkI0cb0KXkY127lDXpi', 'technician', '+254 722 334 455', 'TECH-002', 'Plumbing & Hydraulic Works'),
(4, 'Nicole Net', 'tech.network@fixtrack.campus', '$2y$10$0gfPWphPGO/zm/R9UDxHfeR8tFzTbvFFQMhkI0cb0KXkY127lDXpi', 'technician', '+254 733 445 566', 'TECH-003', 'Campus ICT & Network Systems'),
(5, 'Alice Walker', 'alice.student@fixtrack.campus', '$2y$10$CZEcPD/kZQZsg8IZYPPjPeuxngtU/LF/RpuNNdIwP.0tzfoO9ul9y', 'student', '+254 744 556 677', 'STU-2024-1049', NULL),
(6, 'Brian Kiprono', 'brian.student@fixtrack.campus', '$2y$10$CZEcPD/kZQZsg8IZYPPjPeuxngtU/LF/RpuNNdIwP.0tzfoO9ul9y', 'student', '+254 755 667 788', 'STU-2023-8821', NULL),
(7, 'Claire Mwangi', 'claire.student@fixtrack.campus', '$2y$10$CZEcPD/kZQZsg8IZYPPjPeuxngtU/LF/RpuNNdIwP.0tzfoO9ul9y', 'student', '+254 766 778 899', 'STU-2024-5512', NULL);

-- Facilities
INSERT INTO facilities (id, name, building_code, campus_zone, description, total_floors) VALUES
(1, 'Engineering Complex Block A', 'ENG-B1', 'Engineering Quad', 'Main mechanical, electrical and civil engineering lecture halls and labs', 4),
(2, 'Science & Computing Centre', 'SCI-COMP', 'North Academic Zone', 'Houses university computer science labs, AI research centre, and lecture theatres', 3),
(3, 'Main University Library', 'LIB-MAIN', 'Central Campus Core', 'University central repository, research pods, 24-hour reading areas', 5),
(4, 'Nelson Mandela Hostel Block A', 'HOSTEL-A', 'Residential East', 'Undergraduate student residence hall with 400 rooms', 6),
(5, 'Student Union Centre & Dining', 'STUDENT-CTR', 'Student Life Zone', 'Cafeterias, indoor auditorium, student council offices, recreation halls', 2),
(6, 'Health Sciences Building', 'HLTH-SCI', 'Medical Campus', 'Laboratories, nursing simulation suites and pharmacy lecture halls', 3);

-- Categories (All 8 mandatory requirements)
INSERT INTO categories (id, name, slug, description, icon, default_priority, default_sla_hours) VALUES
(1, 'Electrical problems', 'electrical', 'Exposed wires, socket sparks, circuit breaker trips, main panel faults', 'bolt', 'high', 24),
(2, 'Internet and network problems', 'internet-network', 'Wi-Fi dead zones, broken LAN jacks, access point dropouts, router failures', 'wifi', 'medium', 48),
(3, 'Laboratory equipment failures', 'lab-equipment', 'Centrifuges, oscilloscopes, fume hoods, microscopes, specialized instruments', 'flask', 'high', 48),
(4, 'Classroom equipment failures', 'classroom-equipment', 'Projectors, smart whiteboards, PA audio systems, podium controllers', 'presentation', 'medium', 36),
(5, 'Plumbing and water problems', 'plumbing-water', 'Burst pipes, clogged drainage, running flush tanks, dry taps, sewage leaks', 'droplet', 'high', 24),
(6, 'Furniture damage', 'furniture', 'Broken lecture seats, detached desk boards, broken lab stools, door locks', 'armchair', 'low', 72),
(7, 'Lighting problems', 'lighting', 'Flickering fluorescent tubes, blown floodlights, stairway darkness', 'sun', 'medium', 48),
(8, 'General infrastructure problems', 'general-infrastructure', 'Ceiling tile water stains, broken stair treads, window panes, walkway cracks', 'building', 'low', 96);

-- Sample Reports
INSERT INTO reports (id, tracking_code, student_id, category_id, facility_id, room_number, title, description, priority, status, deadline, created_at) VALUES
(1, 'FTC-2026-10001', 5, 1, 1, 'Lab E-204', 'Burned Wall Socket & Sparking Hazard', 'The electrical wall socket next to workbench #4 started emitting smoke and sparking when power was connected to test benches.', 'critical', 'IN PROGRESS', DATE_ADD(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'FTC-2026-10002', 5, 2, 2, 'Lab 3 (AI Lab)', 'Ethernet Drop Switch Failure - Port 12-24 Dead', 'Students cannot access internal campus repository; switch rack port lights are red and no DHCP lease is provided.', 'high', 'ASSIGNED', DATE_ADD(NOW(), INTERVAL 24 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'FTC-2026-10003', 6, 5, 4, '3rd Floor Restroom', 'Overflowing Drain & Leaking Pipe Under Basin', 'Water is pooling across the 3rd floor west wing hallway due to cracked discharge elbow beneath washbasin 2.', 'critical', 'APPROVED', DATE_ADD(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(4, 'FTC-2026-10004', 6, 4, 1, 'Lecture Theatre 1', 'Main Laser Projector Lamp Failure', 'The ceiling projector powers on for 30 seconds, displays orange temp error light, then immediately shuts down during lectures.', 'medium', 'UNDER REVIEW', DATE_ADD(NOW(), INTERVAL 48 HOUR), DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(5, 'FTC-2026-10005', 7, 7, 3, '2nd Floor Quiet Study Pod', 'Flickering High-Bay LED Fixture', 'Constant buzzing noise and intense strobe flickering from the overhead fixture above carrels 14-20.', 'low', 'REPORTED', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(6, 'FTC-2026-10006', 5, 5, 5, 'Cafeteria Kitchen Prep Area', 'Water Filter Pressure Valve Burst', 'Main commercial pre-rinse sprayer leaking high-pressure hot water onto food preparation counter.', 'critical', 'RESOLVED', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(7, 'FTC-2026-10007', 7, 6, 2, 'Room 102', 'Row 3 Lecture Chairs Loose from Floor Bolts', 'Anchor bolts sheared off when students sat down, causing four attached folding chairs to tip backward.', 'medium', 'CLOSED', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
(8, 'FTC-2026-10008', 6, 8, 3, 'Library Ground Entrance', 'Cracked Glass Door Panel on Emergency Exit', 'Heavy wind slammed the fire door against concrete stop, fracturing the reinforced safety glass pane.', 'high', 'REJECTED', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Set resolution / close timestamps for 6 & 7
UPDATE reports SET resolved_at = DATE_SUB(NOW(), INTERVAL 6 HOUR) WHERE id = 6;
UPDATE reports SET resolved_at = DATE_SUB(NOW(), INTERVAL 6 DAY), verified_at = DATE_SUB(NOW(), INTERVAL 5 DAY), closed_at = DATE_SUB(NOW(), INTERVAL 5 DAY) WHERE id = 7;
UPDATE reports SET rejection_reason = 'Duplicate report. Facilities team is already replacing the exit glass under work order WO-9042.' WHERE id = 8;

-- Assignments
INSERT INTO assignments (id, report_id, technician_id, assigned_by, assignment_notes, assigned_at, status) VALUES
(1, 1, 2, 1, 'Critical electrical hazard. Replace breaker and rewire bench circuit. Follow campus electrical safety protocol.', DATE_SUB(NOW(), INTERVAL 36 HOUR), 'in_progress'),
(2, 2, 4, 1, 'Test patch cables and Cisco Catalyst edge switch. Replace SFP or power module if needed.', DATE_SUB(NOW(), INTERVAL 18 HOUR), 'pending'),
(3, 6, 3, 1, 'Urgent cafeteria kitchen repair completed. Replace brass inlet valve and seal gasket.', DATE_SUB(NOW(), INTERVAL 2 DAY), 'completed'),
(4, 7, 2, 1, 'Repair loose anchors and re-bolt frame to slab.', DATE_SUB(NOW(), INTERVAL 7 DAY), 'completed');

-- Status History (Every workflow change tracked!)
INSERT INTO status_history (report_id, changed_by, old_status, new_status, notes, created_at) VALUES
-- Report 1
(1, 5, NULL, 'REPORTED', 'Initial issue reported by student via mobile portal.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 1, 'REPORTED', 'UNDER REVIEW', 'Admin initiated preliminary assessment.', DATE_SUB(NOW(), INTERVAL 40 HOUR)),
(1, 1, 'UNDER REVIEW', 'APPROVED', 'Electrical risk confirmed. Fast-tracked for emergency response.', DATE_SUB(NOW(), INTERVAL 38 HOUR)),
(1, 1, 'APPROVED', 'ASSIGNED', 'Assigned to Samuel Sparks (Electrical Lead) with 12h deadline.', DATE_SUB(NOW(), INTERVAL 36 HOUR)),
(1, 2, 'ASSIGNED', 'IN PROGRESS', 'Technician arrived on site. Power isolated at distribution board E-DB2.', DATE_SUB(NOW(), INTERVAL 24 HOUR)),

-- Report 2
(2, 5, NULL, 'REPORTED', 'Submitted with test switch screenshots.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 1, 'REPORTED', 'APPROVED', 'Admin approved without delay due to ongoing CS practical exams.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(2, 1, 'APPROVED', 'ASSIGNED', 'Assigned to Nicole Net with high priority.', DATE_SUB(NOW(), INTERVAL 18 HOUR)),

-- Report 3
(3, 6, NULL, 'REPORTED', 'Reported with warning of hallway slip hazard.', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(3, 1, 'REPORTED', 'APPROVED', 'Immediate plumbing dispatch authorized.', DATE_SUB(NOW(), INTERVAL 4 HOUR)),

-- Report 4
(4, 6, NULL, 'REPORTED', 'Submitted by student after lecture interruption.', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(4, 1, 'REPORTED', 'UNDER REVIEW', 'Checking warranty and projector spare bulb inventory.', DATE_SUB(NOW(), INTERVAL 6 HOUR)),

-- Report 5
(5, 7, NULL, 'REPORTED', 'New report received.', DATE_SUB(NOW(), INTERVAL 2 HOUR)),

-- Report 6
(6, 5, NULL, 'REPORTED', 'Cafeteria plumbing hazard reported.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, 1, 'REPORTED', 'ASSIGNED', 'Directly dispatched Peter Pipes.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 3, 'ASSIGNED', 'IN PROGRESS', 'Shut off main riser valve and fitted replacement gasket.', DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(6, 3, 'IN PROGRESS', 'RESOLVED', 'Completed pressure testing. Pressure held at 4.2 bar with zero leakage. Ready for student verification.', DATE_SUB(NOW(), INTERVAL 6 HOUR)),

-- Report 7
(7, 7, NULL, 'REPORTED', 'Loose chairs in Room 102 reported.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(7, 1, 'REPORTED', 'ASSIGNED', 'Assigned to maintenance technician.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(7, 2, 'ASSIGNED', 'IN PROGRESS', 'Work underway with Hilti anchor bolts.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(7, 2, 'IN PROGRESS', 'RESOLVED', 'All 4 chairs anchored with M10 expansion anchors.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(7, 7, 'RESOLVED', 'VERIFIED', 'Student confirmed chairs are solidly secured. Excellent repair.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7, 1, 'VERIFIED', 'CLOSED', 'Ticket officially closed by system.', DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- Report 8
(8, 6, NULL, 'REPORTED', 'Broken glass door panel reported.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8, 1, 'REPORTED', 'REJECTED', 'Duplicate report. Facilities team is already replacing the exit glass under work order WO-9042.', DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Repair Verifications
INSERT INTO repair_verifications (report_id, student_id, is_satisfied, rating, feedback_notes, verified_at) VALUES
(7, 7, 1, 5, 'The technician did a fantastic job. The seats are very sturdy now and safe to use.', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Notifications
INSERT INTO notifications (user_id, report_id, title, message, type, is_read, created_at) VALUES
(5, 1, 'Technician Assigned', 'Your issue "Burned Wall Socket & Sparking Hazard" (FTC-2026-10001) has been assigned to Samuel Sparks.', 'assignment', 1, DATE_SUB(NOW(), INTERVAL 36 HOUR)),
(5, 1, 'Work In Progress', 'Technician Samuel Sparks has started work on your report FTC-2026-10001.', 'status_change', 1, DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(4, 2, 'New Task Assigned', 'You have been assigned to FTC-2026-10002: Ethernet Drop Switch Failure at Science & Computing Centre.', 'assignment', 0, DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(5, 6, 'Repair Completed - Verification Requested', 'Your reported issue FTC-2026-10006 at Cafeteria Kitchen has been resolved. Please test and verify the fix.', 'verification_needed', 0, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(6, 8, 'Report Update: Rejected', 'Your report FTC-2026-10008 was reviewed and rejected. Reason: Duplicate report already under work order WO-9042.', 'rejection', 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 3, 'Critical Report Submitted', 'New critical plumbing issue FTC-2026-10003 submitted in Nelson Mandela Hostel Block A.', 'critical_alert', 0, DATE_SUB(NOW(), INTERVAL 5 HOUR));
