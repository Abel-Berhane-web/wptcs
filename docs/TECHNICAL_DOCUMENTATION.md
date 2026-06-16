# WPTCS — Technical Documentation
## Web-Based Parent-Teacher Communication System
### Felege Tibeb Beata LeMariam Academy, Gondar, Ethiopia

**Version:** 1.0.0  
**Last Updated:** April 2026  
**Technology Stack:** PHP 8.1+ | MySQL 8.x | Bootstrap 5.3

---

## Table of Contents

1. [System Architecture](#1-system-architecture)
2. [Technology Stack](#2-technology-stack)
3. [Directory Structure](#3-directory-structure)
4. [Database Schema](#4-database-schema)
5. [Authentication & Security](#5-authentication--security)
6. [Routing System](#6-routing-system)
7. [Module Reference](#7-module-reference)
8. [Helper Functions API](#8-helper-functions-api)
9. [Internationalization (i18n)](#9-internationalization-i18n)
10. [Installation Guide](#10-installation-guide)
11. [Configuration Reference](#11-configuration-reference)
12. [API Endpoints (AJAX)](#12-api-endpoints-ajax)
13. [Error Handling](#13-error-handling)
14. [Performance Considerations](#14-performance-considerations)

---

## 1. System Architecture

### 1.1 High-Level Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                      CLIENT LAYER                            │
│  ┌──────────┐   ┌──────────────┐   ┌──────────────────┐     │
│  │  Browser  │   │ Bootstrap 5  │   │ JavaScript/AJAX  │     │
│  └─────┬────┘   └──────────────┘   └──────────────────┘     │
└────────┼─────────────────────────────────────────────────────┘
         │  HTTP Request
┌────────▼─────────────────────────────────────────────────────┐
│                      SERVER LAYER                            │
│  ┌───────────┐   ┌──────────┐   ┌──────────────────────┐    │
│  │ index.php │──▶│  Auth    │──▶│   PHP Module Files   │    │
│  │  Router   │   │ Middleware│   │  (28 module pages)   │    │
│  └───────────┘   └──────────┘   └──────────┬───────────┘    │
└─────────────────────────────────────────────┼────────────────┘
                                              │
┌─────────────────────────────────────────────▼────────────────┐
│                       DATA LAYER                             │
│  ┌────────────┐   ┌──────────────┐   ┌──────────────────┐   │
│  │  MySQL 8.x │   │ PHP Sessions │   │   File Storage   │   │
│  │ (17 tables)│   │              │   │   (uploads/)     │   │
│  └────────────┘   └──────────────┘   └──────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

### 1.2 Design Pattern

The system follows a **page-based MVC-lite** architecture:

- **Model:** Direct PDO queries within each module file (no ORM)
- **View:** PHP templates with embedded HTML (header.php / footer.php wrapper)
- **Controller:** Each module file acts as its own controller, handling both GET (display) and POST (action) requests

### 1.3 Request Lifecycle

```
Browser                index.php           Session          Auth          Module         Database
   │                      │                  │               │              │               │
   │── GET ?page=... ────▶│                  │               │              │               │
   │                      │── initSession()─▶│               │              │               │
   │                      │◀─ session ok ────│               │              │               │
   │                      │── checkAuth() ──────────────────▶│              │               │
   │                      │◀─ role=teacher ─────────────────│              │               │
   │                      │── require_once ─────────────────────────────▶│               │
   │                      │                                    │             │── PDO query ─▶│
   │                      │                                    │             │◀─ results ───│
   │◀── rendered HTML ────│◀────────────────────────────────────────────────│               │
```

---

## 2. Technology Stack

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Backend | PHP | 8.1+ | Server-side logic |
| Database | MySQL | 8.x | Data storage |
| DB Driver | PDO | Built-in | Database abstraction |
| Frontend Framework | Bootstrap | 5.3.2 | Responsive UI |
| Icons | Bootstrap Icons | 1.11.2 | UI icons |
| Fonts | Google Fonts | — | Inter + Noto Sans Ethiopic |
| Server | Apache (WAMP) | 2.4+ | Web server |
| JavaScript | Vanilla JS | ES6 | Client-side interactivity |

**IMPORTANT:** No PHP frameworks (Laravel, Symfony, etc.) are used. This is a **pure PHP** application by design, making it lightweight and easy to deploy in environments with limited resources.

---

## 3. Directory Structure

```
wptcs/
├── index.php                    # Main router / entry point
├── config/
│   ├── config.php               # Application constants & settings
│   └── database.php             # PDO connection (singleton pattern)
├── includes/
│   ├── auth.php                 # Authentication & role validation
│   ├── session.php              # Session management & flash messages
│   ├── functions.php            # 30+ helper functions
│   ├── header.php               # HTML head + top navbar + sidebar include
│   ├── sidebar.php              # Role-based sidebar navigation
│   └── footer.php               # Script loading + closing tags
├── modules/
│   ├── auth/
│   │   ├── login.php            # Login form + authentication
│   │   ├── logout.php           # Session destruction
│   │   └── forgot-password.php  # Password reset request
│   ├── admin/
│   │   ├── dashboard.php        # Stats cards + quick actions
│   │   ├── users.php            # CRUD for all users
│   │   ├── students.php         # Student registration + assignment
│   │   ├── sections.php         # Section management + homeroom
│   │   ├── subjects.php         # Bilingual subject management
│   │   ├── assign-teachers.php  # Teacher-Subject-Section mapping
│   │   ├── academic-years.php   # School year management
│   │   └── audit-log.php        # System activity viewer
│   ├── teacher/
│   │   ├── dashboard.php        # Dual homeroom/subject view
│   │   ├── my-homeroom-classes.php  # Homeroom class roster
│   │   ├── my-subject-classes.php   # Subject assignment list
│   │   ├── enter-marks.php      # AJAX marks entry (0-100)
│   │   ├── attendance.php       # Daily attendance taking
│   │   └── weekly-reports.php   # 17-category star ratings
│   ├── parent/
│   │   ├── dashboard.php        # Children cards + announcements
│   │   ├── my-children.php      # Children list view
│   │   ├── view-marks.php       # Report card display
│   │   ├── view-reports.php     # Weekly report viewer
│   │   └── view-attendance.php  # Monthly attendance calendar
│   ├── principal/
│   │   ├── dashboard.php        # School-wide statistics
│   │   ├── announcements.php    # CRUD for announcements
│   │   └── reports.php          # Performance analytics
│   └── shared/
│       ├── comments.php         # Parent-teacher messaging
│       ├── profile.php          # User profile + password change
│       └── announcements-view.php   # Read-only announcement feed
├── assets/
│   ├── css/style.css            # 600+ line design system
│   ├── js/main.js               # AJAX, sidebar toggle, star ratings
│   └── uploads/                 # User-uploaded files
├── lang/
│   ├── en.php                   # English translations (~300 keys)
│   └── am.php                   # Amharic translations (~300 keys)
├── sql/
│   ├── schema.sql               # Database schema (17 tables)
│   └── seed.sql                 # Sample data (10 students, etc.)
└── docs/                        # Documentation files
```

---

## 4. Database Schema

### 4.1 Table Reference

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | All system users | user_id, username, password, role, is_active |
| `grades` | Grade levels (KG–8) | grade_id, grade_name, grade_order |
| `academic_years` | School years | year_id, year_name, is_current |
| `sections` | Class sections | section_id, grade_id, homeroom_teacher_id |
| `students` | Student records | student_id, student_code, parent_id, section_id |
| `subjects` | Academic subjects (14 subjects for KG-G8) | subject_id, subject_name, subject_name_am |
| `teacher_subjects` | Teacher-Subject-Section assignments | teacher_id, subject_id, section_id |
| `assessment_types` | Test, Mid Exam, Assignment, Final | type_id, type_name, weight |
| `marks` | Student scores (0–100) | student_id, subject_id, assessment_type_id, score |
| `attendance` | Daily attendance records | student_id, attendance_date, status |
| `weekly_reports` | Behavioral metrics (JSON) | student_id, teacher_id, metrics, week_number |
| `weekly_report_categories` | 17 behavior categories | category_id, category_name, category_name_am |
| `comments` | Parent-teacher messages | sender_id, receiver_id, student_id, message |
| `notifications` | In-app notifications | user_id, title, message, is_read |
| `announcements` | School announcements | title, content, type, target_audience |
| `homework` | Weekly homework/exercises per section | teacher_id, section_id, subject_id, title, description |
| `audit_log` | Action tracking | user_id, action, table_name, ip_address |
| `password_resets` | Reset tokens | email, token, expires_at |

### 4.2 Entity Relationships

```
USERS ──┬── has many ── STUDENTS (as parent)
        ├── has many ── SECTIONS (as homeroom teacher)
        ├── has many ── TEACHER_SUBJECTS (as teacher)
        ├── has many ── COMMENTS (as sender/receiver)
        └── has many ── NOTIFICATIONS

GRADES ── has many ── SECTIONS

ACADEMIC_YEARS ── has many ── SECTIONS

SECTIONS ──┬── has many ── STUDENTS
           ├── has many ── TEACHER_SUBJECTS
           ├── has many ── MARKS
           └── has many ── ATTENDANCE

SUBJECTS ──┬── has many ── TEACHER_SUBJECTS
           └── has many ── MARKS

STUDENTS ──┬── has many ── MARKS
           ├── has many ── ATTENDANCE
           ├── has many ── WEEKLY_REPORTS
           └── has many ── COMMENTS

ASSESSMENT_TYPES ── has many ── MARKS
```

### 4.3 Assessment Weight Distribution

| Type | Weight | Description |
|------|--------|-------------|
| Test 1 | 20% | First class test |
| Test 2 | 20% | Second class test |
| Group Work | 20% | Group assignments & projects |
| Final Exam | 40% | End-of-semester exam |
| **Total** | **100%** | |

### 4.4 Weekly Report Categories (17 total)

Punctuality, Homework Completion, Class Participation, Respect for Teachers, Respect for Peers, Uniform Compliance, Cleanliness, Obedience, Attentiveness, Book Handling, Material Preparedness, Group Work, Language Use, Discipline, Emotional Behavior, Sports Participation, Overall Conduct

Each rated on a **1–5 star scale**.

---

## 5. Authentication & Security

### 5.1 Authentication Flow

```
Login Form
    │
    ▼
Validate CSRF Token ──── Invalid ──▶ Error: CSRF
    │
    ▼ Valid
Check Lockout ──── Locked ──▶ Error: Account Locked (15 min)
    │
    ▼ OK
Verify Credentials ──── Invalid ──▶ Increment Attempts
    │                                    │
    │                                    ▼ > 5 attempts
    │                               Lock Account
    ▼ Valid
Create Session
    │
    ▼
Set Session Variables
    │
    ▼
Redirect to Role Dashboard
```

### 5.2 Security Measures

| Measure | Implementation |
|---------|---------------|
| **Password Hashing** | Secure hashing via `password_hash()` (BCrypt algorithm) |
| **Password Recovery** | Temporary password generation + secure email via Composer PHPMailer |
| **CSRF Protection** | Token-per-session, validated on every POST |
| **SQL Injection** | PDO prepared statements throughout |
| **XSS Prevention** | `htmlspecialchars()` via `e()` helper on all output |
| **Session Security** | Regenerating IDs, 2-hour timeout, HTTP-only cookies |
| **Brute Force** | 5 attempts → 15-minute lockout |
| **Input Sanitization** | `sanitize()` strips tags + encodes entities |
| **Role-Based Access** | `requireRole()` enforced at top of every module |
| **Marks Edit Window** | 48-hour limit on score modifications |
| **File Upload** | Extension whitelist + size limit (5MB) |

### 5.3 Role Hierarchy

```
Admin ──────── Full system access (users, students, sections, subjects, audit)
Principal ──── Announcements + school-wide reports
Teacher ────── Marks, attendance, weekly reports, comments
Parent ─────── View-only (marks, attendance, reports) + comments
```

### 5.4 Key Auth Functions

```php
requireLogin()                    // Redirects to login if not authenticated
requireRole(string $role)         // Requires specific role, redirects if mismatch
isHomeroomTeacher($tid, $sid)     // Validates homeroom teacher for section
isSubjectTeacher($tid, $subid, $sid) // Validates subject teacher for section
validateCSRFToken(string $token)  // Validates CSRF token against session
csrfField()                       // Returns hidden input with CSRF token
```

---

## 6. Routing System

### 6.1 URL Structure

```
/wptcs/index.php?page={module}/{action}&{params}
```

Examples:
```
/wptcs/index.php?page=admin/dashboard
/wptcs/index.php?page=teacher/enter-marks&section_id=1&subject_id=3
/wptcs/index.php?page=parent/view-marks&student_id=5&semester=1
/wptcs/index.php?page=shared/comments&student_id=2&receiver_id=3
/wptcs/index.php?lang=am   (language switch)
```

### 6.2 Route Map (28 routes)

| Route Key | File | Auth Required |
|-----------|------|:---:|
| `login` | modules/auth/login.php | No |
| `auth/logout` | modules/auth/logout.php | No |
| `auth/forgot-password` | modules/auth/forgot-password.php | No |
| `admin/dashboard` | modules/admin/dashboard.php | Admin |
| `admin/users` | modules/admin/users.php | Admin |
| `admin/students` | modules/admin/students.php | Admin |
| `admin/sections` | modules/admin/sections.php | Admin |
| `admin/subjects` | modules/admin/subjects.php | Admin |
| `admin/assign-teachers` | modules/admin/assign-teachers.php | Admin |
| `admin/academic-years` | modules/admin/academic-years.php | Admin |
| `admin/audit-log` | modules/admin/audit-log.php | Admin |
| `teacher/dashboard` | modules/teacher/dashboard.php | Teacher |
| `teacher/my-homeroom-classes` | modules/teacher/my-homeroom-classes.php | Teacher |
| `teacher/my-subject-classes` | modules/teacher/my-subject-classes.php | Teacher |
| `teacher/enter-marks` | modules/teacher/enter-marks.php | Teacher |
| `teacher/attendance` | modules/teacher/attendance.php | Teacher |
| `teacher/weekly-reports` | modules/teacher/weekly-reports.php | Teacher |
| `parent/dashboard` | modules/parent/dashboard.php | Parent |
| `parent/my-children` | modules/parent/my-children.php | Parent |
| `parent/view-marks` | modules/parent/view-marks.php | Parent |
| `parent/view-reports` | modules/parent/view-reports.php | Parent |
| `parent/view-attendance` | modules/parent/view-attendance.php | Parent |
| `principal/dashboard` | modules/principal/dashboard.php | Principal |
| `principal/announcements` | modules/principal/announcements.php | Principal |
| `principal/reports` | modules/principal/reports.php | Principal |
| `shared/comments` | modules/shared/comments.php | Any |
| `shared/profile` | modules/shared/profile.php | Any |
| `shared/announcements-view` | modules/shared/announcements-view.php | Any |

---

## 7. Module Reference

### 7.1 Admin Modules

#### `admin/users.php` — User Management
- **GET:** Displays user list with role filter, search, pagination
- **POST (create):** Hashes password, inserts user, logs audit
- **POST (edit):** Updates user, optional password change
- **GET (delete):** Soft-deactivates user via `is_active = 0`

#### `admin/students.php` — Student Management
- Auto-generates student codes in format `FTBLM-{YEAR}-{SEQ}`
- Links students to parents and sections
- Validates section capacity before assignment

#### `admin/sections.php` — Section Management
- Requires homeroom teacher assignment
- Displays student count and capacity progress bar
- Filters by grade and academic year

#### `admin/assign-teachers.php` — Teacher Assignment
- Maps teacher → subject → section for the current academic year
- Supports assigning multiple subjects/sections to a single teacher simultaneously via dynamic form rows
- Split-view layout: form (left) + current assignments (right)
- Prevents duplicate assignments

### 7.2 Teacher Modules

#### `teacher/enter-marks.php` — Marks Entry
- AJAX-based POST to save individual scores
- Validates: `0 ≤ score ≤ 100`
- Enforces 48-hour edit window via `canEditMarks()`
- Validates teacher is assigned to the subject-section pair

#### `teacher/attendance.php` — Attendance
- Homeroom teachers only (`isHomeroomTeacher()` validation)
- Quick-set buttons: "All Present" / "All Absent"
- One record per student per date (UPSERT logic)
- Status options: Present, Absent, Late, Excused + optional reason

#### `teacher/weekly-reports.php` — Weekly Reports
- 17 behavioral categories with 1–5 star rating each
- Metrics stored as JSON: `{"1": 4, "2": 5, "3": 3, ...}`
- Optional overall comment field
- Automatically creates notification for parent

#### `teacher/homework.php` — Homework & Exercises
- Post weekly homework/exercises for an entire section at once
- Select subject + section, write title and full description
- Set week number and due date
- Automatically notifies all parents of students in that section
- Teachers can delete their own posts

### 7.3 Parent Modules

#### `parent/view-marks.php` — Report Card
- Weighted score calculation per subject
- Grand average across all subjects
- Semester filter (Semester 1 / Semester 2)
- Printable layout with `window.print()` support

#### `parent/view-attendance.php` — Attendance View
- Monthly calendar view (excludes weekends)
- Summary cards: Present, Absent, Late, Excused
- Color-coded status badges

### 7.4 Shared Modules

#### `shared/comments.php` — Messaging System
- Student-linked conversations (parent <-> teacher)
- AJAX message sending with real-time append
- Unread message count badges
- Chat bubble UI (sent = right, received = left)
- Auto-creates notification for receiver

---

## 8. Helper Functions API

### Core Functions (includes/functions.php)

```php
// Translation
__(string $key, array $params = []): string

// Security
e(string $string): string              // HTML escape
sanitize(string $input): string         // Strip tags + encode
hashPassword(string $password): string  // bcrypt hash
csrfField(): string                     // CSRF hidden input
validateCSRFToken(string $token): bool

// Database
getCurrentAcademicYearId(): ?int
getCurrentAcademicYearName(): ?string

// Notifications
createNotification(int $userId, string $title, string $message, string $type, ?string $link): void
getUnreadNotificationCount(int $userId): int
getUnreadCommentsCount(int $userId): int

// Audit
logAudit(int $userId, string $action, ?string $tableName, ?int $recordId, ?array $oldValues, ?array $newValues): void

// File Upload
uploadFile(array $file, string $destination, array $allowedExtensions = []): array
// Returns: ['success' => bool, 'filename' => string, 'message' => string]

// Display
formatDate(string $date, string $format = 'M d, Y'): string
formatDateTime(string $datetime): string
timeAgo(string $datetime): string
starRating(int $rating, int $max = 5): string
getAttendanceBadgeClass(string $status): string
getGradeDisplayName(string $gradeName, string $sectionName = ''): string

// Navigation
buildUrl(string $page, array $params = []): string
paginate(int $totalRecords, int $currentPage, int $perPage): array
renderPagination(array $pagination, string $baseUrl): string

// Email
sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool
sendWelcomeEmail(string $email, string $firstName, string $lastName, string $username, string $plainPassword, string $role): bool
// Utility
generateRandomPassword(int $length = 10): string
getCurrentLanguage(): string
```

### Auth Functions (includes/auth.php)

```php
authenticateUser(string $username, string $password): array
isLoggedIn(): bool
requireLogin(): void
requireRole(string $role): void
getCurrentUserId(): int
getCurrentUserRole(): string
isHomeroomTeacher(int $teacherId, int $sectionId): bool
isSubjectTeacher(int $teacherId, int $subjectId, int $sectionId): bool
```

### Session Functions (includes/session.php)

```php
initSession(): void
setFlashMessage(string $type, string $message): void
getFlashMessage(): ?array
```

---

## 9. Internationalization (i18n)

### 9.1 Implementation

- Language files in `lang/en.php` and `lang/am.php` return associative arrays
- Translation via `__('key')` function call
- Language preference stored in session + database
- Switched via `?lang=en` or `?lang=am` URL parameter
- CSS class `lang-am` on `<body>` for Amharic-specific font styling

### 9.2 Adding a New Language

1. Copy `lang/en.php` to `lang/{code}.php`
2. Translate all ~300 string values
3. Add `'{code}'` to `SUPPORTED_LANGUAGES` array in `config/config.php`
4. Add language option in `header.php` dropdown

### 9.3 Font Support

```css
.lang-am {
    font-family: 'Noto Sans Ethiopic', 'Inter', sans-serif;
}
```

---

## 10. Installation Guide

### 10.1 Prerequisites

- WAMP / XAMPP / LAMP stack
- PHP 8.1 or higher
- MySQL 8.x
- Apache web server

### 10.2 Steps

```
1. Clone/copy the wptcs/ folder to your web root
   e.g., c:\wamp64\www\Web-based Parent to Teacher Communication System\wptcs\

2. Create database and import schema
   mysql -u root < wptcs/sql/schema.sql

3. Import seed data (optional, for demo)
   mysql -u root < wptcs/sql/seed.sql

4. Update database credentials if needed
   Edit: config/database.php (default: root with no password)

5. Update BASE_URL if path differs
   Edit: config/config.php -> BASE_URL constant

6. Ensure uploads directory is writable
   chmod 755 wptcs/assets/uploads/

7. Access the system
   http://localhost/Web-based Parent to Teacher Communication System/wptcs/
```

### 10.3 Default Credentials

All accounts use password: `Admin@123` *(Note: These are securely hashed in the database)*

| Username | Role | Name |
|----------|------|------|
| admin | Admin | System Administrator |
| principal | Principal | Ato Getachew |
| t.bekele | Teacher | Bekele Tadesse |
| t.almaz | Teacher | Almaz Worku |
| t.dawit | Teacher | Dawit Hailu |
| p.abebe | Parent | Abebe Kebede |
| p.tigist | Parent | Tigist Mengistu |
| p.solomon | Parent | Solomon Girma |
| p.meron | Parent | Meron Assefa |
| p.yonas | Parent | Yonas Tesfaye |

---

## 11. Configuration Reference

**File:** `config/config.php`

| Constant | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `WPTCS` | Application short name |
| `SCHOOL_NAME` | `Felege Tibeb Beata LeMariam Academy` | School name |
| `BASE_URL` | `/Web-based Parent to.../wptcs` | Base URL path |
| `SESSION_TIMEOUT` | `7200` (2 hrs) | Session expiry in seconds |
| `BCRYPT_COST` | `12` | Password hashing cost |
| `MAX_LOGIN_ATTEMPTS` | `5` | Before lockout |
| `LOCKOUT_DURATION` | `900` (15 min) | Lockout time |
| `MARK_EDIT_WINDOW` | `172800` (48 hrs) | Marks edit window |
| `MAX_UPLOAD_SIZE` | `5242880` (5 MB) | Upload size limit |
| `RECORDS_PER_PAGE` | `20` | Pagination default |
| `APP_TIMEZONE` | `Africa/Addis_Ababa` | Server timezone |
| `DEFAULT_LANGUAGE` | `en` | Fallback language |

**File:** `config/database.php`

| Setting | Default |
|---------|---------|
| Host | `localhost` |
| Database | `wptcs_db` |
| Username | `root` |
| Password | *(empty)* |
| Charset | `utf8mb4` |

---

## 12. API Endpoints (AJAX)

These endpoints accept POST requests and return JSON:

### Marks Entry
```
POST /index.php?page=teacher/enter-marks
Content-Type: application/x-www-form-urlencoded

Params: csrf_token, student_id, subject_id, section_id, 
        assessment_type_id, semester, score
Response: { "success": true, "message": "..." }
```

### Attendance Save
```
POST /index.php?page=teacher/attendance
Content-Type: application/x-www-form-urlencoded

Params: csrf_token, section_id, date, 
        attendance[student_id][status], attendance[student_id][reason]
Response: { "success": true, "message": "..." }
```

### Comment Send
```
POST /index.php?page=shared/comments
Content-Type: application/x-www-form-urlencoded

Params: csrf_token, student_id, receiver_id, message
Response: { "success": true, "comment": { "message": "...", "time": "..." } }
```

---

## 13. Error Handling

| Layer | Strategy |
|-------|----------|
| Database | try/catch PDOException -> flash message + error_log |
| Auth | Role mismatch -> redirect to own dashboard |
| 404 | Unknown route -> styled 404 page |
| CSRF | Invalid token -> flash error "Session expired" |
| Validation | Client-side (HTML5 required/min/max) + Server-side |
| File Upload | Extension + size check -> error array return |

---

## 14. Performance Considerations

| Aspect | Implementation |
|--------|---------------|
| DB Connection | Singleton pattern (single connection per request) |
| Indexes | On all foreign keys + frequently queried columns |
| Queries | Prepared statements cached by PDO |
| Assets | CDN for Bootstrap + Icons (cached by browser) |
| Sessions | File-based (default PHP handler) |
| Pagination | Server-side with LIMIT/OFFSET |

For production deployment, consider adding: OPcache for PHP, query result caching, CDN for custom assets, and HTTPS via Let's Encrypt.
