<?php
/**
 * Authentication Functions
 * WPTCS - Web-based Parent Teacher Communication System
 */

/**
 * Authenticate user login
 */
function authenticateUser(string $username, string $password): array {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => __('login_failed')];
    }
    
    // Check if account is active
    if (!$user['is_active']) {
        return ['success' => false, 'message' => __('account_inactive')];
    }
    
    // Check lockout
    if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
            return ['success' => false, 'message' => __('account_locked')];
        } else {
            // Reset lockout
            $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE user_id = :id");
            $stmt->execute([':id' => $user['user_id']]);
        }
    }
    
    // Verify password using password_verify
    if (!password_verify($password, $user['password'])) {
        $attempts = $user['failed_login_attempts'] + 1;
        $lockout = null;
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockout = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        }
        
        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts, lockout_until = :lockout WHERE user_id = :id");
        $stmt->execute([
            ':attempts' => $attempts,
            ':lockout' => $lockout,
            ':id' => $user['user_id']
        ]);
        
        return ['success' => false, 'message' => __('login_failed')];
    }
    
    // Successful login
    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL, last_login = NOW() WHERE user_id = :id");
    $stmt->execute([':id' => $user['user_id']]);
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['language'] = $user['language_pref'] ?? DEFAULT_LANGUAGE;
    $_SESSION['profile_picture'] = $user['profile_picture'];
    $_SESSION['logged_in'] = true;
    
    // Regenerate session ID
    session_regenerate_id(true);
    
    // Log audit
    logAudit($user['user_id'], 'login', 'users', $user['user_id']);
    
    return ['success' => true, 'message' => __('login_success'), 'user' => $user];
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get current user ID
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Check if current user has a specific role
 */
function hasRole(string ...$roles): bool {
    $currentRole = getCurrentUserRole();
    return in_array($currentRole, $roles);
}

/**
 * Require login - redirect if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlashMessage('warning', __('session_expired'));
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }
}

/**
 * Require specific role(s)
 */
function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        http_response_code(403);
        setFlashMessage('danger', __('unauthorized'));
        header('Location: ' . BASE_URL . '/index.php?page=' . getCurrentUserRole() . '/dashboard');
        exit;
    }
}

/**
 * Check if teacher is homeroom teacher for a section
 */
function isHomeroomTeacher(int $teacherId, int $sectionId): bool {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE section_id = :section_id AND homeroom_teacher_id = :teacher_id AND status = 'active'");
    $stmt->execute([':section_id' => $sectionId, ':teacher_id' => $teacherId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Check if teacher is assigned as subject teacher for a section
 */
function isSubjectTeacher(int $teacherId, int $subjectId, int $sectionId): bool {
    $pdo = getDBConnection();
    $currentYear = getCurrentAcademicYearId();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = :teacher_id AND subject_id = :subject_id AND section_id = :section_id AND academic_year_id = :year_id");
    $stmt->execute([
        ':teacher_id' => $teacherId,
        ':subject_id' => $subjectId,
        ':section_id' => $sectionId,
        ':year_id' => $currentYear
    ]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken(string $token): bool {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    $valid = hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    // Regenerate token after validation
    unset($_SESSION[CSRF_TOKEN_NAME]);
    return $valid;
}

/**
 * Get CSRF token HTML input field
 */
function csrfField(): string {
    $token = generateCSRFToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}
