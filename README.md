# Web-Based Parent-Teacher Communication System (WPTCS)

A high-performance monolithic PHP MVC web application designed for **Felege Tibeb Beata LeMariam Academy** in Gondar, Ethiopia.

## Features

- **Multi-Role Dashboards**: Custom interfaces for Admins, Principals, Teachers, and Parents.
- **Bilingual Support**: Dynamic, on-the-fly language switching between **English** and **Amharic (አማርኛ)**.
- **Academic Grading Engine**: Secure entry of numeric continuous assessments (0-100) with a 48-hour editing window.
- **Attendance Tracker**: Efficient daily attendance logs with visual presence statistics.
- **Weekly Behavior Evaluations**: Dynamic rating (1-5 stars) across 17 behavioral categories stored as JSON payloads.
- **Real-Time Communication**: Contextual comment threads between parents and teachers using instant 3-second AJAX polling.
- **PHPMailer SMTP Integration**: Automates welcome credential mailings and password recovery workflows via Google SMTP.
- **Security & Accountability**: Cryptographic hashing (BCRYPT), brute-force account lockouts, CSRF protection, and thorough SQL action audit logs.
- **Bulk Data Migration**: Import and export rosters of students and parent contacts using UTF-8 CSVs.

## Installation & Setup

1. **Requirements**:
   - PHP 8.x or higher
   - MySQL 5.7+ / MariaDB
   - Apache Web Server (e.g. WAMP, XAMPP)
   - Composer (for dependency management)

2. **Database Setup**:
   - Create a database named `wptcs_db`.
   - Import the schema using `/wptcs/sql/schema.sql` (and `/wptcs/sql/seed.sql` for demo credentials).

3. **Composer Installation**:
   - Navigate to the `wptcs` directory and run:
     ```bash
     composer install
     ```

4. **Environment Configuration**:
   - Configure database credentials in `/wptcs/config/database.php`.
   - Update SMTP settings for PHPMailer inside `/wptcs/includes/functions.php`.

5. **Local Execution**:
   - Place the project folder into your web server's root directory (`www/` or `htdocs/`).
   - Open your browser and navigate to: `http://localhost/Web-based Parent to Teacher Communication System/wptcs/index.php`

## Demo Accounts

- **Admin**: `admin` / `Admin@123`
- **Principal**: `principal` / `Admin@123`
- **Teacher**: `t.dawit` / `Admin@123`
- **Parent**: `p.meron` / `Admin@123`
