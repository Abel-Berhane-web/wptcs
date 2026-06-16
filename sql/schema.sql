-- ═══════════════════════════════════════════════════════════════
-- Web-Based Parent-Teacher Communication System (WPTCS)
-- Felege Tibeb Beata LeMariam Academy - Gondar, Ethiopia
-- Database Schema - All 17 Tables
-- ═══════════════════════════════════════════════════════════════

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+03:00";

CREATE DATABASE IF NOT EXISTS `wptcs_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `wptcs_db`;

-- ───────────────────────────────────────────────────────────────
-- Table 1: academic_years
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `academic_years` (
    `year_id` INT PRIMARY KEY AUTO_INCREMENT,
    `year_name` VARCHAR(20) NOT NULL COMMENT 'e.g. 2025-2026',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `is_current` TINYINT(1) DEFAULT 0,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_year_name` (`year_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 2: users (all roles: admin, principal, teacher, parent)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `users` (
    `user_id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `role` ENUM('admin', 'principal', 'teacher', 'parent') NOT NULL,
    `gender` ENUM('male', 'female') DEFAULT NULL,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `failed_login_attempts` INT DEFAULT 0,
    `lockout_until` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `language_pref` ENUM('en', 'am') DEFAULT 'en',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_username` (`username`),
    UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 3: grades (KG1, KG2, KG3, 1-8)
-- Admin can add/edit grades dynamically
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `grades` (
    `grade_id` INT PRIMARY KEY AUTO_INCREMENT,
    `grade_name` VARCHAR(10) NOT NULL COMMENT 'KG1, KG2, KG3, 1, 2, ..., 8',
    `grade_order` INT NOT NULL COMMENT 'For sorting: 0=KG1, 1=KG2, 2=KG3, 3=Grade1...',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    UNIQUE KEY `unique_grade_name` (`grade_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 4: sections (each section has ONE homeroom teacher)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `sections` (
    `section_id` INT PRIMARY KEY AUTO_INCREMENT,
    `section_name` VARCHAR(10) NOT NULL COMMENT 'A, B, C, etc.',
    `grade_id` INT NOT NULL,
    `academic_year_id` INT NOT NULL,
    `homeroom_teacher_id` INT NOT NULL COMMENT 'Homeroom teacher (required)',
    `capacity` INT DEFAULT 40,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`grade_id`) REFERENCES `grades`(`grade_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`year_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_section` (`grade_id`, `section_name`, `academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 5: students
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `students` (
    `student_id` INT PRIMARY KEY AUTO_INCREMENT,
    `student_code` VARCHAR(20) NOT NULL COMMENT 'Unique student ID code',
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('male', 'female') NOT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `parent_id` INT DEFAULT NULL,
    `section_id` INT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'transferred', 'graduated') DEFAULT 'active',
    `promotion_status` ENUM('pending', 'passed', 'failed') DEFAULT 'pending' COMMENT 'End-of-year result',
    `enrollment_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_student_code` (`student_code`),
    FOREIGN KEY (`parent_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 6: subjects
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `subjects` (
    `subject_id` INT PRIMARY KEY AUTO_INCREMENT,
    `subject_name` VARCHAR(100) NOT NULL,

    `subject_code` VARCHAR(10) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_subject_code` (`subject_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 7: teacher_subjects (assigns subject teachers to sections)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `teacher_subjects` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `teacher_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `academic_year_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`year_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_assignment` (`teacher_id`, `subject_id`, `section_id`, `academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 8: assessment_types
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `assessment_types` (
    `type_id` INT PRIMARY KEY AUTO_INCREMENT,
    `type_name` VARCHAR(50) NOT NULL,

    `weight` DECIMAL(5,2) NOT NULL COMMENT 'Percentage weight e.g. 20.00',
    `max_score` DECIMAL(5,2) DEFAULT 100.00,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    UNIQUE KEY `unique_type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 9: marks (numeric scores only, 0-100)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `marks` (
    `mark_id` INT PRIMARY KEY AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `assessment_type_id` INT NOT NULL,
    `academic_year_id` INT NOT NULL,
    `semester` ENUM('1', '2') NOT NULL DEFAULT '1',
    `score` DECIMAL(5,2) NOT NULL COMMENT 'Numeric score 0-100',
    `entered_by` INT NOT NULL COMMENT 'Teacher who entered',
    `is_locked` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE,
    FOREIGN KEY (`assessment_type_id`) REFERENCES `assessment_types`(`type_id`) ON DELETE CASCADE,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`year_id`) ON DELETE CASCADE,
    FOREIGN KEY (`entered_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_mark` (`student_id`, `subject_id`, `assessment_type_id`, `academic_year_id`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 10: weekly_report_categories (17 categories)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `weekly_report_categories` (
    `category_id` INT PRIMARY KEY AUTO_INCREMENT,
    `category_name` VARCHAR(100) NOT NULL,

    `sort_order` INT DEFAULT 0,
    `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 11: weekly_reports (created by homeroom teachers only)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `weekly_reports` (
    `report_id` INT PRIMARY KEY AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `teacher_id` INT NOT NULL COMMENT 'Must be section homeroom teacher',
    `section_id` INT NOT NULL,
    `week_number` TINYINT NOT NULL,
    `report_year` YEAR NOT NULL,
    `metrics` JSON NOT NULL COMMENT 'Stores 17 category ratings (1-5)',
    `overall_comment` TEXT,
    `character_theme` VARCHAR(255) DEFAULT NULL COMMENT 'Character theme discussed in the week',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_weekly_report` (`student_id`, `week_number`, `report_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 12: attendance (taken by homeroom teachers only)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `attendance` (
    `attendance_id` INT PRIMARY KEY AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status` ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    `reason` TEXT,
    `recorded_by` INT NOT NULL COMMENT 'Must be section homeroom teacher',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_attendance` (`student_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 13: announcements
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `announcements` (
    `announcement_id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `type` ENUM('general', 'exam_schedule', 'meeting', 'event') DEFAULT 'general',
    `target_audience` ENUM('all', 'teachers', 'parents', 'specific_grade', 'specific_parent') DEFAULT 'all',
    `target_grade_id` INT DEFAULT NULL,
    `target_student_id` INT DEFAULT NULL COMMENT 'For specific_parent targeting',
    `attachment` VARCHAR(255) DEFAULT NULL,
    `posted_by` INT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `publish_date` DATE DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`posted_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`target_grade_id`) REFERENCES `grades`(`grade_id`) ON DELETE SET NULL,
    FOREIGN KEY (`target_student_id`) REFERENCES `students`(`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 14: comments (parent-teacher communication)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `comments` (
    `comment_id` INT PRIMARY KEY AUTO_INCREMENT,
    `student_id` INT NOT NULL COMMENT 'Linked to a specific student',
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `parent_comment_id` INT DEFAULT NULL COMMENT 'For threaded replies',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_comment_id`) REFERENCES `comments`(`comment_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 15: notifications
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `notifications` (
    `notification_id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 16: audit_log
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `audit_log` (
    `log_id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50) DEFAULT NULL,
    `record_id` INT DEFAULT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Table 17: password_resets
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `password_resets` (
    `reset_id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `is_used` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────
-- Additional Indexes for Performance
-- ───────────────────────────────────────────────────────────────
CREATE INDEX `idx_users_role` ON `users`(`role`);
CREATE INDEX `idx_users_active` ON `users`(`is_active`);
CREATE INDEX `idx_students_parent` ON `students`(`parent_id`);
CREATE INDEX `idx_students_section` ON `students`(`section_id`);
CREATE INDEX `idx_students_status` ON `students`(`status`);
CREATE INDEX `idx_sections_grade` ON `sections`(`grade_id`);
CREATE INDEX `idx_sections_year` ON `sections`(`academic_year_id`);
CREATE INDEX `idx_sections_homeroom` ON `sections`(`homeroom_teacher_id`);
CREATE INDEX `idx_marks_student` ON `marks`(`student_id`);
CREATE INDEX `idx_marks_subject` ON `marks`(`subject_id`);
CREATE INDEX `idx_marks_year` ON `marks`(`academic_year_id`);
CREATE INDEX `idx_attendance_date` ON `attendance`(`attendance_date`);
CREATE INDEX `idx_attendance_student` ON `attendance`(`student_id`);
CREATE INDEX `idx_weekly_reports_student` ON `weekly_reports`(`student_id`);
CREATE INDEX `idx_weekly_reports_teacher` ON `weekly_reports`(`teacher_id`);
CREATE INDEX `idx_comments_student` ON `comments`(`student_id`);
CREATE INDEX `idx_comments_sender` ON `comments`(`sender_id`);
CREATE INDEX `idx_comments_receiver` ON `comments`(`receiver_id`);
CREATE INDEX `idx_notifications_user` ON `notifications`(`user_id`);
CREATE INDEX `idx_notifications_read` ON `notifications`(`is_read`);
CREATE INDEX `idx_audit_user` ON `audit_log`(`user_id`);
CREATE INDEX `idx_audit_action` ON `audit_log`(`action`);

-- ───────────────────────────────────────────────────────────────
-- Table 18: homework (weekly homework/exercises posted by teachers)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE `homework` (
    `homework_id` INT PRIMARY KEY AUTO_INCREMENT,
    `teacher_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `academic_year_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `due_date` DATE DEFAULT NULL,
    `week_number` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`year_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_homework_section` ON `homework`(`section_id`);
CREATE INDEX `idx_homework_teacher` ON `homework`(`teacher_id`);

COMMIT;
