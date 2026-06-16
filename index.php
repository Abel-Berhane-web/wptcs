<?php
/**
 * Main Router
 * WPTCS - Web-based Parent Teacher Communication System
 * 
 * All requests are routed through this file
 */

// Load configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Initialize session
initSession();

// Handle language switching
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES)) {
    $_SESSION['language'] = $_GET['lang'];
    // Update user preference in DB if logged in
    if (isLoggedIn()) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET language_pref = :lang WHERE user_id = :id");
        $stmt->execute([':lang' => $_GET['lang'], ':id' => getCurrentUserId()]);
    }
    // Redirect back to same page without lang param
    $redirect = $_SERVER['REQUEST_URI'];
    $redirect = preg_replace('/[?&]lang=[^&]*/', '', $redirect);
    $redirect = preg_replace('/\?&/', '?', $redirect);
    $redirect = rtrim($redirect, '?');
    if (empty($redirect) || $redirect === BASE_URL . '/index.php') {
        $redirect = BASE_URL . '/index.php?page=' . (isLoggedIn() ? getCurrentUserRole() . '/dashboard' : 'login');
    }
    header('Location: ' . $redirect);
    exit;
}

// Handle AJAX notifications read
if (isset($_GET['action']) && $_GET['action'] === 'mark_notifs_read') {
    if (isLoggedIn()) {
        $pdo = getDBConnection();
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")->execute([':uid' => getCurrentUserId()]);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Determine the page to load
$page = $_GET['page'] ?? '';

// Default routing
if (empty($page)) {
    if (isLoggedIn()) {
        $page = getCurrentUserRole() . '/dashboard';
    } else {
        $page = 'login';
    }
}

// Route mapping
$routes = [
    // Auth routes (public)
    'login' => 'modules/auth/login.php',
    'auth/login' => 'modules/auth/login.php',
    'auth/logout' => 'modules/auth/logout.php',
    'auth/forgot-password' => 'modules/auth/forgot-password.php',
    
    // Admin routes
    'admin/dashboard' => 'modules/admin/dashboard.php',
    'admin/users' => 'modules/admin/users.php',
    'admin/students' => 'modules/admin/students.php',
    'admin/sections' => 'modules/admin/sections.php',
    'admin/subjects' => 'modules/admin/subjects.php',
    'admin/grades' => 'modules/admin/grades.php',
    'admin/assign-teachers' => 'modules/admin/assign-teachers.php',
    'admin/academic-years' => 'modules/admin/academic-years.php',
    'admin/import-export' => 'modules/admin/import-export.php',
    'admin/promotions' => 'modules/admin/promotions.php',
    'admin/audit-log' => 'modules/admin/audit-log.php',
    
    // Teacher routes
    'teacher/dashboard' => 'modules/teacher/dashboard.php',
    'teacher/my-homeroom-classes' => 'modules/teacher/my-homeroom-classes.php',
    'teacher/my-subject-classes' => 'modules/teacher/my-subject-classes.php',
    'teacher/enter-marks' => 'modules/teacher/enter-marks.php',
    'teacher/weekly-reports' => 'modules/teacher/weekly-reports.php',
    'teacher/attendance' => 'modules/teacher/attendance.php',
    'teacher/homework' => 'modules/teacher/homework.php',
    
    // Parent routes
    'parent/dashboard' => 'modules/parent/dashboard.php',
    'parent/my-children' => 'modules/parent/my-children.php',
    'parent/view-marks' => 'modules/parent/view-marks.php',
    'parent/view-reports' => 'modules/parent/view-reports.php',
    'parent/view-attendance' => 'modules/parent/view-attendance.php',
    'parent/homework' => 'modules/parent/homework.php',
    
    // Principal routes
    'principal/dashboard' => 'modules/principal/dashboard.php',
    'principal/announcements' => 'modules/principal/announcements.php',
    'principal/reports' => 'modules/principal/reports.php',
    
    // Shared routes
    'shared/comments' => 'modules/shared/comments.php',
    'shared/profile' => 'modules/shared/profile.php',
    'shared/announcements-view' => 'modules/shared/announcements-view.php',
];

// Check if route exists
if (isset($routes[$page])) {
    $filePath = __DIR__ . '/' . $routes[$page];
    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        http_response_code(404);
        $pageTitle = '404 - Not Found';
        include INCLUDES_PATH . '/header.php';
        echo '<div class="text-center py-5"><h2>404</h2><p>' . __('not_found') . '</p><a href="' . BASE_URL . '" class="btn btn-primary">' . __('home') . '</a></div>';
        include INCLUDES_PATH . '/footer.php';
    }
} else {
    http_response_code(404);
    $pageTitle = '404 - Not Found';
    if (isLoggedIn()) {
        include INCLUDES_PATH . '/header.php';
        echo '<div class="text-center py-5"><h2>404</h2><p>' . __('not_found') . '</p><a href="' . buildUrl(getCurrentUserRole() . '/dashboard') . '" class="btn btn-primary">' . __('dashboard') . '</a></div>';
        include INCLUDES_PATH . '/footer.php';
    } else {
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }
}
