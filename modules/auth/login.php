<?php
/**
 * Login Page
 * WPTCS - Web-based Parent Teacher Communication System
 */

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . buildUrl(getCurrentUserRole() . '/dashboard'));
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
    } elseif (empty($username) || empty($password)) {
        setFlashMessage('danger', __('field_required'));
    } else {
        $result = authenticateUser($username, $password);
        if ($result['success']) {
            header('Location: ' . buildUrl(getCurrentUserRole() . '/dashboard'));
            exit;
        } else {
            setFlashMessage('danger', $result['message']);
        }
    }
    // Redirect to prevent form resubmission
    header('Location: ' . buildUrl('login'));
    exit;
}

$pageTitle = __('login_title');
include INCLUDES_PATH . '/header.php';

$flash = getFlashMessage();
?>

<style>
    /* Override default wrapper for full screen split */
    .auth-wrapper {
        padding: 2rem !important;
        margin: 0 !important;
        max-width: 100% !important;
        width: 100vw !important;
        min-height: 100vh !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        background-color: #f3f6f9 !important;
    }

    body {
        margin: 0;
        padding: 0;
        background-color: #f3f6f9;
        font-family: 'Inter', sans-serif;
        overflow-x: hidden;
    }

    .login-card {
        background: #ffffff;
        max-width: 1100px;
        width: 100%;
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.06);
        display: flex;
        overflow: hidden;
        min-height: 650px;
    }

    /* LEFT SIDE - FORM */
    .login-left {
        flex: 1;
        min-width: 400px;
        padding: 3rem 4rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }

    .app-logo-text {
        font-size: 1.4rem;
        font-weight: 800;
        color: #1e1e2d;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 2rem;
    }

    .app-logo-icon {
        color: #4385f5;
        /* Bright blue brand color */
        font-size: 1.8rem;
    }

    .welcome-block {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .welcome-title {
        font-size: 1.7rem;
        font-weight: 700;
        color: #1e1e2d;
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        color: #a1a5b7;
        font-size: 0.95rem;
    }

    .wave-icon {
        display: inline-block;
        animation: wave 2.5s infinite;
        transform-origin: 70% 70%;
    }

    @keyframes wave {
        0% {
            transform: rotate(0.0deg)
        }

        10% {
            transform: rotate(14.0deg)
        }

        20% {
            transform: rotate(-8.0deg)
        }

        30% {
            transform: rotate(14.0deg)
        }

        40% {
            transform: rotate(-4.0deg)
        }

        50% {
            transform: rotate(10.0deg)
        }

        60% {
            transform: rotate(0.0deg)
        }

        100% {
            transform: rotate(0.0deg)
        }
    }

    .form-label {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1e1e2d;
    }

    .form-control {
        border: 1px solid #e4e6ef;
        border-radius: 10px;
        padding: 0.8rem 1rem 0.8rem 2.5rem;
        font-size: 0.95rem;
        transition: all 0.2s;
        background-color: #ffffff;
    }

    .form-control:focus {
        border-color: #4385f5;
        box-shadow: 0 0 0 0.25rem rgba(67, 133, 245, 0.1);
    }

    .input-icon-left {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #a1a5b7;
        z-index: 10;
    }

    .btn-primary-custom {
        background-color: #2b70fa;
        border: none;
        border-radius: 10px;
        padding: 0.85rem;
        font-weight: 600;
        transition: background 0.2s;
    }

    .btn-primary-custom:hover {
        background-color: #1d5ce0;
    }

    .demo-creds {
        border-top: 1px dashed #e4e6ef;
        margin-top: auto;
        /* push to bottom */
        padding-top: 1.5rem;
        font-size: 0.85rem;
    }

    .demo-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.3rem;
    }

    /* RIGHT SIDE - ILLUSTRATION */
    .login-right {
        flex: 1.2;
        background-color: #eaf1fa;
        /* Extremely light blue to match illustration */
        padding: 1rem;
        display: flex;
        align-items: stretch;
    }

    .illustration-box {
        background-color: #eaf1fa;
        /* Base light blue */
        background-image: url('<?= BASE_URL ?>/assets/img/login_illustration.png');
        background-size: cover;
        background-position: center bottom;
        background-repeat: no-repeat;
        border-radius: 20px;
        width: 100%;
        height: 100%;
        position: relative;
    }

    @media (max-width: 900px) {
        .login-right {
            display: none;
        }

        .login-left {
            padding: 2.5rem 2rem;
            max-width: 100%;
        }

        .login-card {
            min-height: auto;
            width: 90%;
        }
    }
</style>

<div class="login-card">
    <!-- Left Side: Form -->
    <div class="login-left">
        <div class="d-flex flex-column align-items-center text-center mb-4">
            <img src="<?= BASE_URL ?>/assets/img/beata_logo.png" alt="School Logo"
                style="max-height: 100px; margin-bottom: 12px; object-fit: contain;">
            <div style="line-height: 1.3;">
                <div
                    style="font-size: 1.15rem; color: #1e1e2d; font-weight: 800; font-family: 'Noto Sans Ethiopic', sans-serif;">
                    ፈለገ ጥበብ በዓታ ለማርያም አካዳሚ</div>
                <div style="font-size: 0.85rem; color: #a1a5b7; font-weight: 600; margin-top: 4px;">Felege Tibeb Beata
                    LeMariam Academy</div>
            </div>
        </div>

        <div class="welcome-block">
            <h2 class="welcome-title">Sign In</h2>
            <p class="welcome-subtitle">Welcome back! Please enter your details <span class="wave-icon">👋</span></p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'session_expired'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= __('session_expired') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= buildUrl('login') ?>" id="loginForm">
            <?= csrfField() ?>

            <div class="mb-4">
                <label for="username" class="form-label"><?= __('username') ?></label>
                <div class="position-relative">
                    <i class="bi bi-envelope input-icon-left"></i>
                    <input type="text" class="form-control" id="username" name="username"
                        placeholder="teacher@school.com" required autocomplete="username" autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label"><?= __('password') ?></label>
                <div class="position-relative">
                    <i class="bi bi-lock input-icon-left"></i>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••"
                        required autocomplete="current-password">
                    <button class="btn btn-sm position-absolute end-0 top-50 translate-middle-y me-2 text-muted"
                        type="button" id="togglePassword" style="border:none;background:transparent;">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label small" style="color: #5e6278;" for="rememberMe">Remember for 30
                        Days</label>
                </div>
                <a href="<?= buildUrl('auth/forgot-password') ?>" class="text-decoration-none small"
                    style="color: #2b70fa; font-weight: 500;">
                    <?= __('forgot_password') ?>
                </a>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-primary-custom text-white mb-4">
                Sign In
            </button>
        </form>

        <!-- Language Switcher -->
        <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>/index.php?page=login&lang=en"
                class="text-decoration-none small mx-2 <?= getCurrentLanguage() === 'en' ? 'fw-bold text-dark' : 'text-muted' ?>">EN</a>
            <span class="text-muted small">|</span>
            <a href="<?= BASE_URL ?>/index.php?page=login&lang=am"
                class="text-decoration-none small mx-2 <?= getCurrentLanguage() === 'am' ? 'fw-bold text-dark' : 'text-muted' ?>">አማ</a>
        </div>
    </div>

    <!-- Right Side: Illustration -->
    <div class="login-right">
        <div class="illustration-box"></div>
    </div>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const password = document.getElementById('password');
        const icon = this.querySelector('i');
        if (password.type === 'password') {
            password.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            password.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
</script>

<?php
// Close the open 'auth-wrapper' from header.php
?>
</div>
</body>

</html>