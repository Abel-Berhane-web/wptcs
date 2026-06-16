# 📘 Technical Blueprint & Architecture Walkthrough
### Project: **Web-Based Parent-Teacher Communication System (WPTCS)**
**School**: *Felege Tibeb Beata LeMariam Academy - Gondar, Ethiopia*
**Document Version**: *1.0.0 (Production-Ready)*
**Author**: *Senior Project Manager & Technical Communicator*

---

## 1. Executive Summary & High-Level System Architecture

The **Web-Based Parent-Teacher Communication System (WPTCS)** is an enterprise-grade, secure, multi-role web platform designed to streamline administrative workflows, simplify student progress tracking, and close the communication gap between educators and parents. It is tailored for the specific educational ecosystem of **Felege Tibeb Beata LeMariam Academy** in Gondar, Ethiopia, supporting bilingual operations in **English** and **Amharic (አማርኛ)**.

### Architectural Philosophy
WPTCS is built on a highly optimized, light-weight, and custom-engineered **monolithic Model-View-Controller (MVC) architecture** using pure PHP, HTML5, CSS3, ES6 JavaScript, and MySQL (InnoDB engine).

Rather than relying on bloated modern frameworks that introduce heavy performance overhead, the system leverages a **Centralized Front Controller Pattern** (routed through `index.php`). This design provides the following architectural advantages:
- **Zero Framework Dependency**: Maximizes execution speed, minimizes external package vulnerabilities, and runs natively on standard Apache/WAMP web servers or cost-effective public hosts (e.g., InfinityFree).
- **Strict Role-Based Access Control (RBAC)**: Enforces access limitations at the controller level before rendering any views, protecting student records and administrative modules from horizontal privilege escalation.
- **State Management & Thread Safety**: Uses secure, HTTP-only session cookies and centralized PDO singleton connection instances to eliminate SQL Injection risks and optimize database pool connections.

```
                  ┌─────────────────────────────────────────────────────────┐
                  │                 USER BROWSER / CLIENT                   │
                  └────────────────────────────┬────────────────────────────┘
                                               │ HTTP/HTTPS Request
                                               ▼
                  ┌─────────────────────────────────────────────────────────┐
                  │           INDEX.PHP (Central Front Router)              │
                  └──────┬─────────────────────┬─────────────────────┬──────┘
                         │                     │                     │
      Config & Database  ▼      Session & Auth ▼            Helper   ▼
     ┌──────────────────────┐ ┌──────────────────────┐ ┌────────────────────┐
     │ • config.php         │ │ • session.php        │ │ • functions.php    │
     │ • database.php (PDO) │ │ • auth.php (RBAC)    │ │ • PHPMailer      │
     └──────────────────────┘ └──────────────────────┘ └────────────────────┘
                                               │
                                               ├───────────────┐
                                               ▼               ▼
                                    ┌─────────────────────┐ ┌─────────────────────┐
                                    │  HTML Header Render │ │ Modules Router Map  │
                                    │    (header.php)     │ │  (Role Directories) │
                                    └─────────────────────┘ └──────────┬──────────┘
                                                                       │
                         ┌────────────────┬────────────────┬───────────┼───────────────┬────────────────┐
                         ▼                ▼                ▼           ▼               ▼                ▼
                  ┌────────────┐   ┌────────────┐   ┌────────────┐  ┌────────────┐  ┌────────────┐   ┌────────────┐
                  │ modules/   │   │ modules/   │   │ modules/   │  │ modules/   │  │ modules/   │   │ modules/   │
                  │   auth/    │   │   admin/   │   │  teacher/  │  │  parent/   │  │ principal/ │   │  shared/   │
                  └────────────┘   └────────────┘   └────────────┘  └────────────┘  └────────────┘   └────────────┘
                                                                       │
                                                                       ▼
                                                    ┌─────────────────────────────────────┐
                                                    │        DATABASE ENGINE (MySQL)      │
                                                    │    (InnoDB Engine with Foreign Keys)│
                                                    └─────────────────────────────────────┘
```

---

## 2. Database Schema, Relations & Entity Design

WPTCS utilizes a fully relational MySQL database consisting of **18 tables** engineered to enforce referential integrity using explicit `FOREIGN KEY` constraints, cascading rules (`ON DELETE CASCADE` / `ON DELETE RESTRICT`), and optimized indexes.

### Entity Relationship & Table Definition Matrix

#### Table 1: `academic_years`
Stores structural academic years. Represents the system's global temporal context.
- **Attributes**:
  - `year_id` (INT, Primary Key, Auto-Increment)
  - `year_name` (VARCHAR(20), Unique Key): e.g., "2025-2026" or "2026-2027"
  - `start_date` (DATE), `end_date` (DATE)
  - `is_current` (TINYINT(1), Default 0): Determines active academic configuration.
  - `status` (ENUM('active', 'inactive'), Default 'active')
  - `created_at` / `updated_at` (TIMESTAMP)

#### Table 2: `users`
Central table holding identity details for all physical users.
- **Attributes**:
  - `user_id` (INT, Primary Key, Auto-Increment)
  - `username` (VARCHAR(50), Unique Key)
  - `password` (VARCHAR(255)): Cryptographic hash generated using `BCRYPT` (Cost 12).
  - `email` (VARCHAR(100), Unique Key)
  - `phone` (VARCHAR(20), Nullable)
  - `first_name` (VARCHAR(50)), `last_name` (VARCHAR(50))
  - `role` (ENUM('admin', 'principal', 'teacher', 'parent'))
  - `gender` (ENUM('male', 'female'), Nullable)
  - `profile_picture` (VARCHAR(255), Nullable)
  - `is_active` (TINYINT(1), Default 1): Administrative switch to block login.
  - `failed_login_attempts` (INT, Default 0)
  - `lockout_until` (DATETIME, Nullable)
  - `last_login` (DATETIME, Nullable)
  - `language_pref` (ENUM('en', 'am'), Default 'en')
  - `created_at` / `updated_at` (TIMESTAMP)

#### Table 3: `grades`
Defines dynamic classes (KG1 to Grade 8).
- **Attributes**:
  - `grade_id` (INT, Primary Key, Auto-Increment)
  - `grade_name` (VARCHAR(10), Unique Key): "KG1", "KG2", "KG3", "1", "2", ..., "8"
  - `grade_order` (INT): Sorting integer (0 = KG1, 1 = KG2, 2 = KG3, 3 = Grade 1, etc.)
  - `status` (ENUM('active', 'inactive'), Default 'active')

#### Table 4: `sections`
Represents subsections (e.g., Grade 8-A, Grade 8-B). Links grade levels, academic years, and homeroom teachers.
- **Attributes**:
  - `section_id` (INT, Primary Key, Auto-Increment)
  - `section_name` (VARCHAR(10)): "A", "B", "C", etc.
  - `grade_id` (INT, Foreign Key references `grades`(`grade_id`) `ON DELETE RESTRICT`)
  - `academic_year_id` (INT, Foreign Key references `academic_years`(`year_id`) `ON DELETE RESTRICT`)
  - `homeroom_teacher_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE RESTRICT`)
  - `capacity` (INT, Default 40)
  - `status` (ENUM('active', 'inactive'), Default 'active')
  - **Unique Constraints**: Unique key on (`grade_id`, `section_name`, `academic_year_id`) to prevent duplicate sections within the same academic cycle.

#### Table 5: `students`
Primary repository of student profiles.
- **Attributes**:
  - `student_id` (INT, Primary Key, Auto-Increment)
  - `student_code` (VARCHAR(20), Unique Key): Formatted string (e.g., `FTBLM-2026-001`).
  - `first_name` (VARCHAR(50)), `last_name` (VARCHAR(50))
  - `gender` (ENUM('male', 'female'))
  - `date_of_birth` (DATE, Nullable)
  - `parent_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE SET NULL`)
  - `section_id` (INT, Foreign Key references `sections`(`section_id`) `ON DELETE SET NULL`)
  - `photo` (VARCHAR(255), Nullable)
  - `status` (ENUM('active', 'inactive', 'transferred', 'graduated'), Default 'active')
  - `promotion_status` (ENUM('pending', 'passed', 'failed'), Default 'pending')
  - `enrollment_date` (DATE, Nullable)

#### Table 6: `subjects`
Defines academic subjects.
- **Attributes**:
  - `subject_id` (INT, Primary Key, Auto-Increment)
  - `subject_name` (VARCHAR(100))
  - `subject_code` (VARCHAR(10), Unique Key): e.g., "MATH01", "AMH01"

#### Table 7: `teacher_subjects`
A highly critical **many-to-many pivot table** mapping teachers to specific subjects within sections for each academic year.
- **Attributes**:
  - `id` (INT, Primary Key, Auto-Increment)
  - `teacher_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE CASCADE`)
  - `subject_id` (INT, Foreign Key references `subjects`(`subject_id`) `ON DELETE CASCADE`)
  - `section_id` (INT, Foreign Key references `sections`(`section_id`) `ON DELETE CASCADE`)
  - `academic_year_id` (INT, Foreign Key references `academic_years`(`year_id`) `ON DELETE CASCADE`)
  - **Unique Constraints**: Unique assignment on (`teacher_id`, `subject_id`, `section_id`, `academic_year_id`) to prevent duplicate assignments.

#### Table 8: `assessment_types`
Determines grading components (e.g., Homework, Mid-Exam, Final-Exam).
- **Attributes**:
  - `type_id` (INT, Primary Key, Auto-Increment)
  - `type_name` (VARCHAR(50), Unique Key): e.g., "Homework (10%)", "Mid-Exam (30%)"
  - `weight` (DECIMAL(5,2)): Numeric percentage (e.g., 20.00)
  - `max_score` (DECIMAL(5,2), Default 100.00)

#### Table 9: `marks`
Stores numeric scores recorded for students.
- **Attributes**:
  - `mark_id` (INT, Primary Key, Auto-Increment)
  - `student_id` (INT, Foreign Key references `students`(`student_id`) `ON DELETE CASCADE`)
  - `subject_id` (INT, Foreign Key references `subjects`(`subject_id`) `ON DELETE CASCADE`)
  - `section_id` (INT, Foreign Key references `sections`(`section_id`) `ON DELETE CASCADE`)
  - `assessment_type_id` (INT, Foreign Key references `assessment_types`(`type_id`) `ON DELETE CASCADE`)
  - `academic_year_id` (INT, Foreign Key references `academic_years`(`year_id`) `ON DELETE CASCADE`)
  - `semester` (ENUM('1', '2'), Default '1')
  - `score` (DECIMAL(5,2)): Real-world score (0.00 to 100.00)
  - `entered_by` (INT, Foreign Key references `users`(`user_id`) `ON DELETE RESTRICT`)
  - `is_locked` (TINYINT(1), Default 0)
  - **Unique Constraints**: Unique key on (`student_id`, `subject_id`, `assessment_type_id`, `academic_year_id`, `semester`) guarantees a student has exactly one record per assessment combination.

#### Table 10: `weekly_report_categories`
Details the behavioral/character metrics (17 standard educational categories) checked during student weekly reporting.
- **Attributes**:
  - `category_id` (INT, Primary Key, Auto-Increment)
  - `category_name` (VARCHAR(100)): Behavioral aspects (e.g., "Honesty", "Class Participation").
  - `sort_order` (INT)
  - `status` (ENUM('active', 'inactive'), Default 'active')

#### Table 11: `weekly_reports`
Weekly evaluation cards compiled by Homeroom Teachers.
- **Attributes**:
  - `report_id` (INT, Primary Key, Auto-Increment)
  - `student_id` (INT, Foreign Key references `students`(`student_id`) `ON DELETE CASCADE`)
  - `teacher_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE RESTRICT`)
  - `section_id` (INT, Foreign Key references `sections`(`section_id`) `ON DELETE CASCADE`)
  - `week_number` (TINYINT)
  - `report_year` (YEAR)
  - `metrics` (JSON): Stores key-value mappings of category IDs to their 1-5 ratings.
  - `overall_comment` (TEXT), `character_theme` (VARCHAR(255), Nullable)
  - **Unique Constraints**: Unique key (`student_id`, `week_number`, `report_year`) ensures only one evaluation per week for a student.

#### Table 12: `attendance`
Stores daily attendance records.
- **Attributes**:
  - `attendance_id` (INT, Primary Key, Auto-Increment)
  - `student_id` (INT, Foreign Key references `students`(`student_id`) `ON DELETE CASCADE`)
  - `section_id` (INT, Foreign Key references `sections`(`section_id`) `ON DELETE CASCADE`)
  - `attendance_date` (DATE)
  - `status` (ENUM('present', 'absent', 'late', 'excused'))
  - `reason` (TEXT, Nullable)
  - `recorded_by` (INT, Foreign Key references `users`(`user_id`) `ON DELETE RESTRICT`)
  - **Unique Constraints**: Unique key (`student_id`, `attendance_date`) prevents multiple records for a student on the same day.

#### Table 13: `announcements`
Houses general announcements, event bulletins, and documents published by administration.
- **Attributes**:
  - `announcement_id` (INT, Primary Key, Auto-Increment)
  - `title` (VARCHAR(255)), `content` (TEXT)
  - `type` (ENUM('general', 'exam_schedule', 'meeting', 'event'))
  - `target_audience` (ENUM('all', 'teachers', 'parents', 'specific_grade', 'specific_parent'))
  - `target_grade_id` (INT, Foreign Key references `grades`(`grade_id`) `ON DELETE SET NULL`)
  - `target_student_id` (INT, Foreign Key references `students`(`student_id`) `ON DELETE SET NULL`)
  - `attachment` (VARCHAR(255), Nullable): Link to uploaded documents/images.
  - `posted_by` (INT, Foreign Key references `users`(`user_id`) `ON DELETE RESTRICT`)
  - `is_active` (TINYINT(1), Default 1)
  - `publish_date` (DATE, Nullable), `expiry_date` (DATE, Nullable)

#### Table 14: `comments`
Stores the messaging threads between teachers and parents associated with specific students.
- **Attributes**:
  - `comment_id` (INT, Primary Key, Auto-Increment)
  - `student_id` (INT, Foreign Key references `students`(`student_id`) `ON DELETE CASCADE`)
  - `sender_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE CASCADE`)
  - `receiver_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE CASCADE`)
  - `message` (TEXT)
  - `is_read` (TINYINT(1), Default 0)
  - `parent_comment_id` (INT, Foreign Key references `comments`(`comment_id`) `ON DELETE SET NULL`)
  - `created_at` (TIMESTAMP)

#### Table 15: `notifications`
Central alert mechanism displaying actions inside the navbar.
- **Attributes**:
  - `notification_id` (INT, Primary Key, Auto-Increment)
  - `user_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE CASCADE`)
  - `title` (VARCHAR(255)), `message` (TEXT)
  - `type` (ENUM('info', 'warning', 'success', 'danger'), Default 'info')
  - `link` (VARCHAR(255), Nullable): Redirection route when clicked.
  - `is_read` (TINYINT(1), Default 0)

#### Table 16: `audit_log`
Chronicles all critical read/write transitions for administrative visibility.
- **Attributes**:
  - `log_id` (INT, Primary Key, Auto-Increment)
  - `user_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE SET NULL`)
  - `action` (VARCHAR(100))
  - `table_name` (VARCHAR(50))
  - `record_id` (INT, Nullable)
  - `old_values` (JSON, Nullable)
  - `new_values` (JSON, Nullable)
  - `ip_address` (VARCHAR(45))
  - `user_agent` (VARCHAR(255))
  - `created_at` (TIMESTAMP)

#### Table 17: `password_resets`
Stores highly secure, expire-controlled password reset tokens.
- **Attributes**:
  - `reset_id` (INT, Primary Key, Auto-Increment)
  - `user_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE CASCADE`)
  - `token` (VARCHAR(255), Unique Key)
  - `expires_at` (DATETIME)
  - `is_used` (TINYINT(1), Default 0)

#### Table 18: `homework`
Facilitates publishing academic exercises and homework schedules.
- **Attributes**:
  - `homework_id` (INT, Primary Key, Auto-Increment)
  - `teacher_id` (INT, Foreign Key references `users`(`user_id`) `ON DELETE CASCADE`)
  - `section_id` (INT, Foreign Key references `sections`(`section_id`) `ON DELETE CASCADE`)
  - `subject_id` (INT, Foreign Key references `subjects`(`subject_id`) `ON DELETE CASCADE`)
  - `academic_year_id` (INT, Foreign Key references `academic_years`(`year_id`) `ON DELETE CASCADE`)
  - `title` (VARCHAR(255)), `description` (TEXT)
  - `due_date` (DATE, Nullable), `week_number` (INT, Nullable)

---

## 3. Directory Configuration & Root Files

The structure is meticulously organized into logical sub-directories under the root `wptcs` folder.

```
📁 wptcs (Root)
 ├── 📄 index.php (Front Router)
 ├── 📄 composer.json / composer.lock
 ├── 📁 assets/
 │    ├── 📁 css/ (style.css)
 │    ├── 📁 js/ (main.js)
 │    ├── 📁 img/ (beata_logo.png, login_illustration.png)
 │    └── 📁 uploads/ (Academic files, parent attachments, avatars)
 ├── 📁 config/
 │    ├── 📄 config.php (Global settings & constants)
 │    └── 📄 database.php (PDO Connection & Environment Detector)
 ├── 📁 docs/
 │    └── 📄 TECHNICAL_DOCUMENTATION.md
 ├── 📁 includes/
 │    ├── 📄 auth.php (Role filters & CSRF triggers)
 │    ├── 📄 footer.php (Structural template closure)
 │    ├── 📄 functions.php (Core engines: PHPMailer SMTP, security)
 │    ├── 📄 header.php (Navbar, HTML structure, Notification AJAX handler)
 │    ├── 📄 session.php (Secure session wrappers & time-out limits)
 │    └── 📄 sidebar.php (Dynamic navigation link builder by role)
 ├── 📁 lang/
 │    ├── 📄 am.php (Amharic Translation dictionary)
 │    └── 📄 en.php (English Translation dictionary)
 ├── 📁 modules/
 │    ├── 📁 admin/ (11 core admin panels)
 │    ├── 📁 auth/ (Login/Logout/Forgot-Password workflows)
 │    ├── 📁 parent/ (6 child tracking dashboards)
 │    ├── 📁 principal/ (3 central reporting interfaces)
 │    ├── 📁 shared/ (Unified communication & profile settings)
 │    └── 📁 teacher/ (7 grading & classroom panels)
 └── 📁 sql/
      ├── 📄 schema.sql (18 database structures)
      └── 📄 seed.sql (Testing and default records)
```

### Script Analysis: Front Router & Setup Configurations

#### 📄 `wptcs/index.php` (Front Controller)
- **Role**: All web traffic is funnelled through this central script.
- **Workflow**:
  1. Bootstraps environment: loads `config.php`, `database.php`, `session.php`, `functions.php`, and `auth.php`.
  2. Runs `initSession()`, configuring secure PHP cookie options.
  3. Checks `$_GET['lang']` to handle dynamic language selection ("en" or "am"). If logged in, updates `language_pref` in the `users` table via PDO, updates session state, and redirects safely.
  4. Intercepts AJAX requests: e.g., if `$_GET['action'] === 'mark_notifs_read'`, immediately flags all user notifications as read in the database and returns a light JSON string (`{"success": true}`).
  5. Determines page target via `$_GET['page']`. Maps request tags (e.g., `admin/users`) against a hardcoded route whitelist (`$routes` array) pointing to localized physical files.
  6. Loads the requested page. If the page is not found in the whitelist, renders a beautiful fallback 404 page within the header/footer scaffolding.

#### 📄 `wptcs/config/config.php`
- **Role**: Declares system-wide immutable configuration parameters.
- **Key Settings**:
  - **Dynamic Base URL**: Inspects `$_SERVER['HTTP_HOST']`. If running locally on a loopback address (`localhost`, `127.0.0.1`), automatically configures absolute subfolders (`/Web-based Parent to Teacher Communication System/wptcs`). If deployed on a live domain, sets the root path to adapt cleanly without manual modification.
  - **Security Constants**: Enforces a `BCRYPT_COST` of 12 for password hashing, `MAX_LOGIN_ATTEMPTS` to 5, `LOCKOUT_DURATION` to 900 seconds (15 mins), and a rigid mark editing safety window (`MARK_EDIT_WINDOW`) of 172,800 seconds (exactly 48 hours).
  - **File Restrictions**: Maximum upload limit defined globally (`MAX_UPLOAD_SIZE` = 5MB). Allowed extensions specified inside `ALLOWED_EXTENSIONS` (`jpg`, `jpeg`, `png`, `gif`, `pdf`, `doc`, `docx`).
  - **Localization & Grading Defaults**: Configures timezone to `'Africa/Addis_Ababa'` and maps passing marks (`PASS_MARK` = 50).

#### 📄 `wptcs/config/database.php`
- **Role**: Handles highly optimized database connectivity.
- **Workflow**:
  - Automatically checks `$_SERVER['HTTP_HOST']` to toggle between local WAMP MySQL credentials (`root` with no password, pointing to `wptcs_db`) and InfinityFree production variables.
  - Exposes `getDBConnection()` using a strictly governed **Singleton Pattern**. This ensures a single PDO instance is instantiated per page execution, significantly reducing thread overhead and performance lag.
  - Configures the connection with essential settings:
    - Enforces active exception throwing (`PDO::ERRMODE_EXCEPTION`).
    - Disables emulator prepare statements (`PDO::ATTR_EMULATE_PREPARES => false`) to enforce native prepared SQL executions (mitigating SQL Injection exploits).
    - Enforces modern UTF-8 support by executing an initialization command `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci` upon bootstrapping.

---

## 4. `includes/` Directory — Global Framework Functions

The scripts within `wptcs/includes/` form the operational core of the system. They provide security boundaries, helper functions, and rendering layouts.

```
                      ┌─────────────────────────────────┐
                      │    INITIALIZE PAGE REQUEST      │
                      └────────────────┬────────────────┘
                                       │
                                       ▼
                      ┌─────────────────────────────────┐
                      │    session.php: initSession()   │
                      │  - Secure HTTP-only Cookie      │
                      │  - Validate Session Timeout     │
                      │  - 30-min Auto-Regeneration     │
                      └────────────────┬────────────────┘
                                       │
                                       ▼
                      ┌─────────────────────────────────┐
                      │    auth.php: requireLogin()     │
                      │  - Is Logged In?                │
                      │  - Enforce RBAC Role Filters    │
                      └────────────────┬────────────────┘
                                       │
                                       ▼
                      ┌─────────────────────────────────┐
                      │   functions.php & translation   │
                      │  - CSRF Token Validation        │
                      │  - Translate Subject Strings    │
                      │  - Inject Audit Log Record      │
                      └─────────────────────────────────┘
```

### 📄 `wptcs/includes/session.php`
Responsible for secure session state management.
- **`initSession()`**:
  - Enforces highly secure session cookies: `session.cookie_httponly` (shields cookies from malicious Javascript theft/XSS), `session.use_strict_mode` (blocks session fixation attempts), and `session.cookie_samesite` set to `'Lax'` (mitigates CSRF vulnerabilities).
  - Enforces Session Timeout: Calculates difference between current timestamp and `$_SESSION['last_activity']`. If the difference exceeds `SESSION_TIMEOUT` (2 hours), automatically wipes session data and redirects the browser back to the login interface with a descriptive message.
  - Session Hijacking Mitigation: Tracks `$_SESSION['created_at']` and invokes `session_regenerate_id(true)` every 30 minutes, invalidating older session identifiers and locking active sessions to legitimate clients.
- **`destroySession()`**: Safely flushes `$_SESSION` global arrays, deletes physical session cookies from the client browser, and destroys the active session container on the server.
- **Flash Messages**: Exposes simple, reliable redirection banners using `setFlashMessage()` and `getFlashMessage()` to pass success, danger, or warning alerts between script operations.

### 📄 `wptcs/includes/auth.php`
Implements security layers and checks.
- **`authenticateUser()`**:
  - Verifies credentials against DB using `password_verify()`.
  - Implements a secure **Brute-Force Protection & Lockout Lock**: On authentication failure, increments `failed_login_attempts`. If failures reach `MAX_LOGIN_ATTEMPTS` (5), updates `lockout_until` in the database to lock the user out for 15 minutes.
  - Automatically logs audit actions on success.
- **Role Verification**:
  - `isLoggedIn()`, `getCurrentUserId()`, `getCurrentUserRole()`, and `hasRole()`.
  - `requireLogin()`: Instantly redirects unauthorized users to the login screen.
  - `requireRole(string ...$roles)`: Enforces rigid role checks. If a user tries to access an unauthorized route, throws a `403 Forbidden` header, triggers a flash alert, and redirects them to their respective role dashboard.
- **CSRF Mitigation**:
  - Generates secure cryptographic tokens (`generateCSRFToken()`) stored in the user's session.
  - Validates tokens during POST actions (`validateCSRFToken()`) using the timing-attack safe `hash_equals()` function.
  - Automatically rotates/clears tokens once validated.
  - `csrfField()` provides a clean one-line helper to inject hidden input elements directly into forms.

### 📄 `wptcs/includes/functions.php`
An extensive utility file containing core business logic.
- **Bilingual Translation Engine (`__($key, $params)`)**: Exposes a translation function that loads language arrays (`en.php` or `am.php`) dynamically based on active user state. Caches translation arrays using static variables and interpolates dynamic parameters (e.g., `__('welcome_message', ['name' => $userName])`).
- **`translateSubject($subjectName)`**: Translates raw database subject values to corresponding Amharic strings if translation matches exist.
- **Security Sanitization (`e()`, `sanitize()`)**: High-performance wrappers for `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` and `strip_tags()` to block cross-site scripting (XSS) injections in forms and displays.
- **Academic Temporal Wrappers**: Fetch values such as the active year (`getCurrentAcademicYearId()`, `getCurrentAcademicYearName()`).
- **Notification Manager (`createNotification()`)**: Standardized utility to inject database alert records linked to particular users with visual urgency colors (`success`, `warning`, `info`, `danger`) and quick redirection paths.
- **Audit Logging System (`logAudit()`)**: Generates system-wide accountability records, inserting active user details, targeted tables, primary IDs, dynamic JSON snapshots of pre-change (`old_values`) and post-change (`new_values`) records, IP addresses, and user-agent details.
- **File Upload Handler (`uploadFile()`)**: Checks incoming files against `MAX_UPLOAD_SIZE`, verifies file extensions, generates unique filenames using `uniqid()`, and securely stores the uploaded files in `assets/uploads/`.
- **Promotion & Performance Analytics**: Exposes helper functions for calculating average scores, managing promotion flows, and verifying if marks are still editable within the 48-hour window.

### 📄 `wptcs/includes/header.php`
- Defines the HTML5 structure, imports fonts (Inter and Noto Sans Ethiopic), Bootstrap 5 CSS, Bootstrap Icons, and `assets/css/style.css`.
- Dynamically toggles body classes depending on the active language (`lang-en` or `lang-am`) to apply proper typography styling.
- Integrates a real-time responsive sidebar toggle and dropdown selectors for language switches.
- **Notification Dropdown**: Dynamically queries the database for the active user's unread notifications. Includes a real-time javascript fetch endpoint to trigger `markNotificationsRead()` via AJAX, clearing alerts without page reloads.

### 📄 `wptcs/includes/sidebar.php`
- Dynamically compiles sidebar links based on the active user's authenticated role:
  - **Admin**: Dashboard, Manage Users, Manage Students, Manage Sections, Manage Subjects, Manage Grades, Assign Teachers, Academic Years, Import/Export, Promotions, Announcements, Audit Log.
  - **Principal**: Dashboard, Announcements, Reports.
  - **Teacher**: Dashboard, Homeroom Classes, Subject Classes, Attendance, Enter Marks, Weekly Reports, Homework, Comments (with real-time unread badges), Announcements.
  - **Parent**: Dashboard, My Children, View Marks, Weekly Reports, Attendance, Homework, Comments (with real-time unread badges), Announcements.
- Enforces selected menu highlight states using active query routes.

---

## 5. Bilingual System (`lang/` Directory)

The system supports seamless dynamic translation, translating page titles, structural links, input validation errors, and academic terms on the fly.

### 📄 `wptcs/lang/en.php` (English Dictionary)
Exposes an extensive associative array of keys to English strings:
```php
return [
    'dashboard' => 'Dashboard',
    'manage_users' => 'Manage Users',
    'login_title' => 'Sign In | Felege Tibeb Academy',
    'not_found' => 'The requested page was not found.',
    // ... Over 200 operational phrases mapped.
];
```

### 📄 `wptcs/lang/am.php` (Amharic Dictionary)
Exposes the identical set of keys mapped directly to Amharic (Ethiopic) script:
```php
return [
    'dashboard' => 'ዳሽቦርድ',
    'manage_users' => 'ተጠቃሚዎችን ማስተዳደር',
    'login_title' => 'ይግቡ | ፈለገ ጥበብ በዓታ ለማርያም አካዳሚ',
    'not_found' => 'የጠየቁት ገጽ አልተገኘም።',
    // ... Fully localized for local parents and staff.
];
```

---

## 6. Functional Module Analysis (`modules/` Directory)

Every script inside the modules directory implements a dedicated user interface and processing logic.

### 📁 `modules/auth/` — Session Boundaries & Recovery

#### 📄 `login.php`
- Renders a clean sign-in screen split into a responsive forms card and a custom vector illustration.
- Employs double security validation (CSRF token verification and sanitization).
- Validates passwords against BCRYPT hashes and logs session variables (`user_id`, `role`, `language_pref`, `full_name`, etc.) upon successful authentication.
- Automatically handles failed authentication logging, tracking attempts, and enforcing IP and account-level lockouts.

#### 📄 `logout.php`
- Destroys active session variables, cleans server handles, logs the logout activity in the audit logs, and redirects the client to the login screen.

#### 📄 `forgot-password.php`
- Handles forgot password flows.
- Validates submitted emails, generates a safe 32-character token, inserts it with an expiration timestamp into the `password_resets` table, and sends a password recovery email to the user.

---

### 📁 `modules/admin/` — Master School Control Panel

This directory houses the school's central administrative features.

```
                         ADMINISTRATIVE CONTROL PANELS
 ┌─────────────────────────────────────────────────────────────────────────┐
 │ • dashboard.php: School-wide statistics & real-time audit stream        │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • users.php: CRUD interface for school staff & parents (BCRYPT, Email)  │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • students.php: Dynamic enrollment, parent matching, student code gen   │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • sections.php: Define dynamic sections & assign homeroom teachers      │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • import-export.php: Bulk upload students/parents via structured CSV    │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • promotions.php: Multi-level academic grade promotions (Grades 1-8)    │
 └─────────────────────────────────────────────────────────────────────────┘
```

#### 📄 `dashboard.php`
- Renders administrative analytics panels displaying total enrolled users, parents, teachers, and active sections.
- Embeds visual statistics cards detailing active student gender distribution.
- Queries a live stream of the last 5 logs from the `audit_log` table, providing immediate visibility into system actions.

#### 📄 `users.php`
- Complete Create, Read, Update, Delete (CRUD) interface for school staff and parents.
- Employs BCRYPT hashing for passwords, maps system roles, and handles profile picture uploads with extension checks and dynamic naming.
- Integrates pagination handles (`paginate()` and `renderPagination()`) to efficiently process massive lists.
- Triggers welcome emails via PHPMailer containing generated credentials upon new user creation.

#### 📄 `students.php`
- Coordinates student enrollment and profile updates.
- Auto-generates unique student codes (`generateStudentCode()`) on new enrollments.
- Maps students to their respective parents via relational database foreign keys.
- Handles photo uploads and handles status changes (`active`, `transferred`, `inactive`).

#### 📄 `sections.php`
- Allows administrators to define classes (e.g., Grade 8-A), configure seat capacities, and assign homeroom teachers.
- Features real-time queries that prevent duplicate section names and ensure teachers are not assigned as homeroom teachers to multiple sections.

#### 📄 `subjects.php`
- Configures dynamic educational subjects (e.g., Mathematics, Amharic, Social Studies) and enforces unique course code checks.

#### 📄 `grades.php`
- Configures grade levels and sets their ordering properties (`grade_order`) to control promotional hierarchies.

#### 📄 `assign-teachers.php`
- Handles the assignment of subject teachers to sections for the current academic year.
- Inserts records into the `teacher_subjects` table, allowing teachers to log marks and homework for assigned classes.

#### 📄 `academic-years.php`
- Allows administrators to transition between academic years.
- Validates date ranges and ensures only one academic year is marked as active.

#### 📄 `import-export.php`
- Enforces bulk CSV import and export capabilities.
- Admins can download structured CSV templates and upload rosters of students and parents.
- Leverages `parseCSVUpload()` to parse files, validate column headers, generate user credentials on the fly, send welcome emails, and execute transactional database inserts.

#### 📄 `promotions.php`
- Implements student promotion logic.
- At the end of an academic year, administrators can review students, check averages, and promote students to the next grade or mark them as graduated.

#### 📄 `audit-log.php`
- Provides search and filter capabilities for the system's audit log records.
- Allows administrators to view past actions, including JSON snapshots of old and new data values.

---

### 📁 `modules/teacher/` — Academic Entry & Behavior Records

Provides teachers with tools to manage assigned classes and communicate student progress.

#### 📄 `dashboard.php`
- Tailored home panel for teachers.
- Renders the teacher's active homeroom section details, lists subject classes assigned to them via the `teacher_subjects` relation, and displays a quick menu of unread parent messages.

#### 📄 `my-homeroom-classes.php`
- Lists students in the homeroom class.
- Provides direct links to record daily attendance, enter weekly evaluation cards, and view detailed academic performance charts.

#### 📄 `my-subject-classes.php`
- Lists the sections and subjects assigned to the teacher. Provides direct links to record assessment marks for students.

#### 📄 `attendance.php`
- Daily attendance interface.
- Automatically displays student rosters with status toggles (`Present`, `Absent`, `Late`, `Excused`).
- Enforces the single-record-per-day rule by running `INSERT ... ON DUPLICATE KEY UPDATE` queries.

#### 📄 `enter-marks.php`
- Facilitates recording assessment scores (e.g., Homework, Mid-Exam, Final Exam).
- Features a secure **48-hour editing window** (`canEditMarks()`). Once this threshold passes, scores are locked and can only be unlocked by an administrator.

#### 📄 `weekly-reports.php`
- Allows homeroom teachers to compile weekly evaluation cards.
- Evaluates students on a scale of 1-5 across **17 behavior metrics** (e.g., class participation, punctuality, cooperation, hygiene).
- Encodes evaluations as JSON payloads stored inside the `metrics` column of the `weekly_reports` table, and includes character themes and custom comment fields.

#### 📄 `homework.php`
- Allows teachers to post homework assignments.
- Features description fields, due dates, and document upload capabilities.
- Automatically generates real-time notifications for parents of students in the section.

---

### 📁 `modules/parent/` — Child Progress & Academic Tracking

Tailored for parents, providing real-time visibility into their children's school life.

```
                           PARENT PORTAL MODULES
 ┌─────────────────────────────────────────────────────────────────────────┐
 │ • dashboard.php: Central interface with quick access to children's cards│
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • my-children.php: Renders children profiles & links to class details   │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • view-marks.php: View academic grades & semester performance averages   │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • view-reports.php: View teacher comments & 17-point behavioral metrics │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • view-attendance.php: Track daily attendance & absent/late statistics  │
 ├─────────────────────────────────────────────────────────────────────────┤
 │ • homework.php: Review assigned homework tasks & download instructions  │
 └─────────────────────────────────────────────────────────────────────────┘
```

#### 📄 `dashboard.php`
- Displays summary cards for each child linked to the parent.
- Shows real-time averages, attendance percentages, and quick alerts for recent homework assignments and announcements.

#### 📄 `my-children.php`
- Displays individual profiles for the parent's children.
- Shows quick summaries of assigned sections, teachers, and direct links to academic records.

#### 📄 `view-marks.php`
- Renders detailed grade reports for children.
- Lists scores grouped by assessment type, calculates weighted totals, and displays cumulative semester averages.

#### 📄 `view-reports.php`
- Displays weekly behavior reports.
- Parents can select weeks, view ratings (1-5 stars) across the 17 behavioral categories, and read homeroom teacher comments.

#### 📄 `view-attendance.php`
- Shows child attendance history.
- Displays summary statistics (e.g., total days present, absent, late, or excused) alongside calendar views of past records.

#### 📄 `homework.php`
- Lists homework assigned to children.
- Displays due dates, detailed instructions, and provides download links for attachments.

---

### 📁 `modules/principal/` — School Oversight & Analytics

Designed for school principals to monitor academic performance and coordinate announcements.

#### 📄 `dashboard.php`
- School-wide monitoring dashboard.
- Displays quick counts of enrolled students, teachers, active parents, and averages.
- Lists recent announcements and features quick links to reports.

#### 📄 `announcements.php`
- Allows principals to post target-audience announcements.
- Supports target categories: `all`, `teachers`, `parents`, `specific_grade`, and `specific_parent` (linked to a selected student's parent).
- Integrates dynamic file upload utilities for document attachments.

#### 📄 `reports.php`
- Renders aggregated academic performance reports.
- Displays top-performing classes, student failure rates, and sections with critical attendance trends.

---

### 📁 `modules/shared/` — Cross-Role Unified Tools

Unified components shared across different user roles.

#### 📄 `profile.php`
- Allows users to manage their profiles.
- Users can update contact info, change passwords, and upload custom avatars (validated to prevent directory traversal or malicious script uploads).

#### 📄 `announcements-view.php`
- Displays announcements published by administration or principals.
- Filters announcements based on target audiences appropriate to the logged-in user's role.

#### 📄 `comments.php` (Real-Time Communication Engine)
- The platform's primary parent-teacher communication bridge.
- **Workflow**:
  - Displays list of children linked to parents, and homeroom teachers or class rosters linked to teachers.
  - **Real-Time Polling Engine**: Integrates a client-side JavaScript polling function that queries the server every 3 seconds (`?action=poll_comments`) with a timestamp parameter (`since`).
  - The server checks the database for messages received after the timestamp parameter, marks incoming messages as read, and returns them as a clean JSON payload.
  - The client UI parses the JSON payload and appends new message bubbles to the chat thread dynamically without page refreshes.
  - Submitting a message triggers an AJAX POST request, inserts the comment record, generates database notifications for the recipient, and updates the thread.

```
  Teacher Browser                                                 Server (comments.php)
  ┌────────────────┐                                               ┌─────────────────┐
  │                ├────── AJAX GET (poll_comments every 3s) ─────►│                 │
  │                │       since = '2026-05-29 15:20:00'           │                 │
  │  Active Chat   │                                               │  Query DB for   │
  │     Thread     │◄───── Return JSON payload of new messages ────┤  records since  │
  │                │                                               │  timestamp      │
  │                ├────── AJAX POST (Send Message) ──────────────►│                 │
  │                │       message = "Hello parent..."             │  Insert record  │
  │                │                                               │  & create alert │
  │                │◄───── Return success confirmation ────────────┤  notifications  │
  └────────────────┘                                               └─────────────────┘
```

---

## 7. Email & Communication Infrastructure

The email and notification features keep stakeholders informed of school activities.

### Centralized SMTP PHPMailer Configuration
WPTCS integrates **PHPMailer (v6.x)** to manage system email communications. Rather than configuring SMTP settings across individual files, the application uses a single centralized wrapper function: `sendMail()` defined inside `includes/functions.php`.

```php
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool
{
    // Loads vendor autoloader generated by Composer
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        error_log('PHPMailer not installed. Cannot send email.');
        return false;
    }
    require_once $vendorAutoload;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // SMTP Server credentials and parameters
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Google SMTP Server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alemubela6@gmail.com'; // Sender Email Address
        $mail->Password   = 'tubj tjxp jnxv zhbt'; // Cryptographic Google App Password
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587; // STARTTLS secure port

        $mail->setFrom('alemubela6@gmail.com', 'Felege Tibeb Academy');
        $mail->addAddress($toEmail, $toName);
        $mail->CharSet = 'UTF-8';

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '<br/>'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}
```

### Welcome & Credential Email Flows
When an administrator registers a user or imports a student roster via CSV, the system generates random temporary passwords using `generateRandomPassword()`.

Once the database record is inserted, it calls `sendWelcomeEmail(email, first_name, last_name, username, password, role)`.

This function styles a professional HTML template featuring:
- A linear gradient header incorporating the name and logo of the academy.
- Account role specifications.
- Secure, monospace-styled username and password fields.
- Direct redirection links (`Login Now` buttons) pointing to the system's URL.
- Security instructions prompting users to update their passwords upon their first login.

---

## 8. Security & Auditing Blueprint

WPTCS is built with extensive security features to safeguard student information and prevent unauthorized access.

### 🛡️ Brute-Force Defense & Lockout Policy
To defend against automated credential attacks, the system implements account-level lockouts.
1. The `users` table tracks failed login attempts (`failed_login_attempts`) and lockout timers (`lockout_until`).
2. When authentication fails, the login script increments the failure counter.
3. If failures reach `MAX_LOGIN_ATTEMPTS` (5), the lockout timer is set to 15 minutes (`LOCKOUT_DURATION`).
4. Subsequent login attempts within this window are automatically blocked before processing, protecting server resources.

### 🛡️ CSRF (Cross-Site Request Forgery) Protection
Every dynamic form (including AJAX request endpoints) includes CSRF protection.
- The `csrfField()` helper generates a hidden input element containing a unique token generated using `bin2hex(random_bytes(32))`.
- Upon form submission, `validateCSRFToken()` verifies the submitted token against the session token using the timing-attack safe `hash_equals()` function.
- The validation token is immediately rotated to prevent replay attacks.

### 🛡️ Access Control Middleware
System routes are protected by role-based validation checks.
- Access filters are evaluated at the top of every script using helper functions such as `requireLogin()` and `requireRole('admin', 'teacher')`.
- Attempted access by unauthorized users is recorded in the system audit logs, and redirects the user to their default dashboard.

### 🛡️ State Change Audit Logging
Every data insertion, update, or deletion is recorded in the system's audit trails via the `logAudit()` function.
- Log records capture user IDs, target tables, actions (`create`, `update`, `delete`), and target primary IDs.
- For updates, the system captures pre-change (`old_values`) and post-change (`new_values`) database states as structured JSON payloads.
- Logs capture client IP addresses and user-agent strings, providing administrators with complete audit trail visibility.

---

## 9. File Upload & Storage Management

The file upload system is designed to prevent directory traversal exploits, malicious script uploads, and file collision issues.

### 📁 Validation Mechanics (`uploadFile()` in `includes/functions.php`)
1. **Size Verification**: The system evaluates uploaded file sizes against `MAX_UPLOAD_SIZE` (5MB), rejecting oversized files.
2. **Extension Whitelisting**: Verifies file extensions against whitelists (`ALLOWED_EXTENSIONS`). The system checks mime types and uses PHP's `pathinfo` utility to prevent extension-spoofing attacks (e.g., uploading `exploit.php.jpg`).
3. **Randomized Naming**: To prevent filename collisions and hide raw original filenames, files are renamed using a combination of random numbers and unique identifiers (`uniqid('file_', true)`), resulting in randomized filenames (e.g., `file_69e47b577d3954.78873515.docx`).
4. **Directory Security**: Files are stored in the `assets/uploads/` directory. Upload directories are initialized with restricted permissions (`0755`) to prevent execution capabilities, and include `.gitkeep` files to manage directory availability in repositories.

---

## 💡 Quick Reference: How the System Works (A Complete Walkthrough)

To help your study group understand the platform's operational flow, here is what happens behind the scenes during a typical day-to-day transaction:

### 1. The Welcome & First Login Flow
*   **Step A (Admin Side)**: The administrator logs into the system, navigates to `admin/users`, and adds a new teacher. 
*   **Step B (Under the Hood)**: The system generates a strong, random password. It hashes the password using `password_hash()` (BCRYPT, Cost 12) and saves the user record to the `users` table. It then records this action in the `audit_log` table.
*   **Step C (Email System)**: The system calls `sendWelcomeEmail()`. PHPMailer initializes an SMTP transaction using Google SMTP, sending a beautifully styled credential email to the teacher.
*   **Step D (Teacher Login)**: The teacher opens the link from their email, enters their credentials, and logs in. The system checks if the account is active, compares the entered password against the BCRYPT hash, resets any failed login attempts, updates `last_login`, and initializes a secure session container.

### 2. Enter Grades & Lockdown Window Flow
*   **Step A (Teacher Side)**: An assigned Mathematics teacher navigates to `teacher/enter-marks`, selects Grade 8-A, and records Mid-Exam scores for students.
*   **Step B (Under the Hood)**: The system runs validation checks to ensure the teacher is assigned to teach Mathematics to Grade 8-A for the active academic year (querying the `teacher_subjects` table). It then inserts the scores into the `marks` table.
*   **Step C (Locked Window)**: If the teacher returns within 48 hours to correct a scoring typo, the system evaluates the current timestamp against the record's creation date (`canEditMarks()`) and permits updates. However, once 48 hours pass, the system blocks direct edits. If modifications are required, an administrator must intervene to unlock the record.

### 3. Real-Time Chat & Polling Flow
*   **Step A (Parent Side)**: A parent logs into the parent portal, navigates to the Comments section, selects their child, and sends a message to the child's homeroom teacher.
*   **Step B (Under the Hood)**: The browser submits an AJAX POST request containing a CSRF token. The system validates the token, cleans the message content using `sanitize()`, inserts a record into the `comments` table linked to the student ID, and registers an alert notification for the teacher.
*   **Step C (Real-Time Update)**: On the teacher's browser, a background JavaScript polling function queries the server every 3 seconds (`poll_comments` action). The server queries the database for comments received since the last poll timestamp, marks them as read, and returns them as a JSON payload. The teacher's browser receives the payload and displays the new message bubble instantly, providing a seamless communication experience.
