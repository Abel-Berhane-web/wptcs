-- ═══════════════════════════════════════════════════════════════
-- WPTCS Seed Data
-- ═══════════════════════════════════════════════════════════════
USE `wptcs_db`;

-- ───────────────────────────────────────────────────────────────
-- Academic Years
-- ───────────────────────────────────────────────────────────────
INSERT INTO `academic_years` (`year_name`, `start_date`, `end_date`, `is_current`) VALUES
('2024-2025', '2024-09-11', '2025-07-07', 0),
('2025-2026', '2025-09-11', '2026-07-07', 1);

-- ───────────────────────────────────────────────────────────────
-- Grades (KG1, KG2, KG3, then 1-8)
-- ───────────────────────────────────────────────────────────────
INSERT INTO `grades` (`grade_name`, `grade_order`) VALUES
('KG1', 0),
('KG2', 1),
('KG3', 2),
('1', 3),
('2', 4),
('3', 5),
('4', 6),
('5', 7),
('6', 8),
('7', 9),
('8', 10);

-- ───────────────────────────────────────────────────────────────
-- Users (password: Admin@123 in plain text for testing)
-- ───────────────────────────────────────────────────────────────
INSERT INTO `users` (`username`, `password`, `email`, `phone`, `first_name`, `last_name`, `role`, `gender`) VALUES
-- Admin
('admin', 'Admin@123', 'admin@ftblm.edu.et', '+251911111111', 'System', 'Administrator', 'admin', 'male'),
-- Principal
('principal', 'Admin@123', 'principal@ftblm.edu.et', '+251911222222', 'Ato', 'Getachew', 'principal', 'male'),
-- Teachers (3)
('t.bekele', 'Admin@123', 'bekele@ftblm.edu.et', '+251911333333', 'Bekele', 'Tadesse', 'teacher', 'male'),
('t.almaz', 'Admin@123', 'almaz@ftblm.edu.et', '+251911444444', 'Almaz', 'Worku', 'teacher', 'female'),
('t.dawit', 'Admin@123', 'dawit@ftblm.edu.et', '+251911555555', 'Dawit', 'Hailu', 'teacher', 'male'),
-- Parents (5)
('p.abebe', 'Admin@123', 'abebe@gmail.com', '+251912111111', 'Abebe', 'Kebede', 'parent', 'male'),
('p.tigist', 'Admin@123', 'tigist@gmail.com', '+251912222222', 'Tigist', 'Mengistu', 'parent', 'female'),
('p.solomon', 'Admin@123', 'solomon@gmail.com', '+251912333333', 'Solomon', 'Girma', 'parent', 'male'),
('p.meron', 'Admin@123', 'meron@gmail.com', '+251912444444', 'Meron', 'Assefa', 'parent', 'female'),
('p.yonas', 'Admin@123', 'yonas@gmail.com', '+251912555555', 'Yonas', 'Tesfaye', 'parent', 'male');

-- ───────────────────────────────────────────────────────────────
-- Subjects (14 subjects across KG, Grade 1-6, Grade 7-8)
-- ───────────────────────────────────────────────────────────────
INSERT INTO `subjects` (`subject_name`, `subject_code`) VALUES
('Amharic', 'AMH'),
('English', 'ENG'),
('Mathematics', 'MATH'),
('Science', 'SCI'),
('Geez', 'GEEZ'),
('Art', 'ART'),
('Environmental Science', 'ENV'),
('HPE', 'HPE'),
('IT', 'IT'),
('Moral Education', 'MORAL'),
('Citizenship', 'CITIZEN'),
('Career and Technical Education', 'CTE'),
('Social Studies', 'SOCIAL'),
('General Science', 'GSCI');

-- ───────────────────────────────────────────────────────────────
-- Assessment Types
-- ───────────────────────────────────────────────────────────────
INSERT INTO `assessment_types` (`type_name`, `weight`, `max_score`) VALUES
('Test 1', 20.00, 20.00),
('Test 2', 20.00, 20.00),
('Group Work', 20.00, 20.00),
('Final Exam', 40.00, 40.00);

-- ───────────────────────────────────────────────────────────────
-- Weekly Report Categories (17 categories)
-- ───────────────────────────────────────────────────────────────
INSERT INTO `weekly_report_categories` (`category_name`, `sort_order`) VALUES
('Time Management', 1),
('Cleanliness', 2),
('Reading Ability', 3),
('Handwriting', 4),
('Participation', 5),
('Homework Completion', 6),
('Attendance', 7),
('Eating Habits', 8),
('Communication with Others', 9),
('Material Handling', 10),
('Uniform', 11),
('Hair & Nail Care', 12),
('Physical Exercise', 13),
('Geez Performance', 14),
('English Performance', 15),
('Mathematics Performance', 16),
('Study Habits', 17);

-- ───────────────────────────────────────────────────────────────
-- Sections (sample sections with homeroom teachers)
-- Grade IDs: KG1=1, KG2=2, KG3=3, 1=4, 2=5, 3=6, 4=7, 5=8, 6=9, 7=10, 8=11
-- Teacher IDs: Bekele=3, Almaz=4, Dawit=5
-- ───────────────────────────────────────────────────────────────
INSERT INTO `sections` (`section_name`, `grade_id`, `academic_year_id`, `homeroom_teacher_id`, `capacity`) VALUES
('A', 1, 2, 4, 35),   -- KG1-A, Homeroom: Almaz
('A', 8, 2, 3, 40),   -- Grade 5-A, Homeroom: Bekele
('B', 8, 2, 5, 40),   -- Grade 5-B, Homeroom: Dawit
('A', 10, 2, 3, 38),  -- Grade 7-A, Homeroom: Bekele
('A', 11, 2, 5, 38);  -- Grade 8-A, Homeroom: Dawit

-- ───────────────────────────────────────────────────────────────
-- Students (10 sample students)
-- ───────────────────────────────────────────────────────────────
INSERT INTO `students` (`student_code`, `first_name`, `last_name`, `gender`, `date_of_birth`, `parent_id`, `section_id`, `enrollment_date`) VALUES
('FTBLM-2025-001', 'Kidus', 'Kebede', 'male', '2018-03-15', 6, 1, '2024-09-11'),
('FTBLM-2025-002', 'Hanna', 'Kebede', 'female', '2014-07-22', 6, 2, '2024-09-11'),
('FTBLM-2025-003', 'Naod', 'Mengistu', 'male', '2014-01-10', 7, 2, '2024-09-11'),
('FTBLM-2025-004', 'Bethel', 'Mengistu', 'female', '2012-11-05', 7, 4, '2024-09-11'),
('FTBLM-2025-005', 'Abel', 'Girma', 'male', '2014-06-18', 8, 2, '2024-09-11'),
('FTBLM-2025-006', 'Selam', 'Girma', 'female', '2011-09-30', 8, 5, '2024-09-11'),
('FTBLM-2025-007', 'Dawit', 'Assefa', 'male', '2013-04-12', 9, 3, '2024-09-11'),
('FTBLM-2025-008', 'Feven', 'Assefa', 'female', '2018-08-25', 9, 1, '2024-09-11'),
('FTBLM-2025-009', 'Yared', 'Tesfaye', 'male', '2012-02-14', 10, 4, '2024-09-11'),
('FTBLM-2025-010', 'Rahel', 'Tesfaye', 'female', '2014-12-01', 10, 2, '2024-09-11');

-- ───────────────────────────────────────────────────────────────
-- Teacher-Subject Assignments
-- ───────────────────────────────────────────────────────────────
INSERT INTO `teacher_subjects` (`teacher_id`, `subject_id`, `section_id`, `academic_year_id`) VALUES
(3, 3, 2, 2),  -- Bekele teaches Amharic in Grade 5-A
(3, 3, 4, 2),  -- Bekele teaches Amharic in Grade 7-A
(4, 1, 1, 2),  -- Almaz teaches English in KG1-A
(4, 1, 2, 2),  -- Almaz teaches English in Grade 5-A
(5, 2, 3, 2),  -- Dawit teaches Math in Grade 5-B
(5, 2, 5, 2);  -- Dawit teaches Math in Grade 8-A

-- ───────────────────────────────────────────────────────────────
-- Sample Announcements
-- ───────────────────────────────────────────────────────────────
INSERT INTO `announcements` (`title`, `content`, `type`, `target_audience`, `posted_by`, `publish_date`) VALUES
('Welcome to the New Academic Year 2025-2026', 'Dear parents and teachers, we welcome you all to the new academic year. Let us work together for the success of our students.', 'general', 'all', 2, '2025-09-11'),
('First Semester Mid Exam Schedule', 'Mid-term examinations will be held from November 15 to November 22, 2025. Please ensure students are prepared.', 'exam_schedule', 'all', 2, '2025-11-01'),
('Parent-Teacher Meeting', 'We invite all parents to attend the parent-teacher meeting scheduled for October 15, 2025 at 3:00 PM in the school hall.', 'meeting', 'parents', 2, '2025-10-01');
