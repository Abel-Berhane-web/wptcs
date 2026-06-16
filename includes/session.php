<?php
/**
 * Session Management
 * WPTCS - Web-based Parent Teacher Communication System
 */

// Start session with secure settings
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        
        session_name(SESSION_NAME);
        session_start();
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                destroySession();
                header('Location: ' . BASE_URL . '/index.php?page=login&msg=session_expired');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID periodically (every 30 minutes)
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
        } elseif (time() - $_SESSION['created_at'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created_at'] = time();
        }
    }
}

/**
 * Destroy session completely
 */
function destroySession(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Set flash message in session
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}
