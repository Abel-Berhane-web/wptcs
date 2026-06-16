<?php
/**
 * Application Configuration
 * WPTCS - Web-based Parent Teacher Communication System
 */

// Application Settings
define('APP_NAME', 'Felege Tibeb Academy');
define('APP_FULL_NAME', 'Felege Tibeb Beata LeMariam Academy System');
define('SCHOOL_NAME', 'Felege Tibeb Beata LeMariam Academy');
define('SCHOOL_LOCATION', 'Gondar, Ethiopia');
define('APP_VERSION', '1.0.0');

// Base URL - Auto-detect environment
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if ($is_local) {
    define('BASE_URL', '/Web-based Parent to Teacher Communication System/wptcs');
    define('BASE_URL_ABS', $protocol . $host . '/Web-based Parent to Teacher Communication System/wptcs');
} else {
    define('BASE_URL', ''); // On InfinityFree, the site will be at the root of the domain
    define('BASE_URL_ABS', $protocol . $host);
}

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('LANG_PATH', ROOT_PATH . '/lang');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ASSETS_PATH . '/uploads');

// Session Settings
define('SESSION_TIMEOUT', 7200); // 2 hours in seconds
define('SESSION_NAME', 'WPTCS_SESSION');

// Security Settings
define('BCRYPT_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds
define('MARK_EDIT_WINDOW', 172800); // 48 hours in seconds
define('CSRF_TOKEN_NAME', 'csrf_token');

// File Upload Settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Pagination
define('RECORDS_PER_PAGE', 20);

// Date/Time
define('APP_TIMEZONE', 'Africa/Addis_Ababa');
date_default_timezone_set(APP_TIMEZONE);

// Default Language
define('DEFAULT_LANGUAGE', 'en');
define('SUPPORTED_LANGUAGES', ['en', 'am']);

// Grading System (Numeric only 0-100)
define('MAX_SCORE', 100);
define('MIN_SCORE', 0);
define('PASS_MARK', 50); // Minimum average to pass

// Weekly Report Rating Scale
define('MIN_RATING', 1);
define('MAX_RATING', 5);
