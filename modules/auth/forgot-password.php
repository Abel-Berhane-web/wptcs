<?php
/**
 * Forgot Password Page
 */

if (isLoggedIn()) {
    header('Location: ' . buildUrl(getCurrentUserRole() . '/dashboard'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
    } elseif (empty($email)) {
        setFlashMessage('danger', __('field_required'));
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND is_active = 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Since passwords are now hashed, we cannot send the existing password.
            // Generate a random temporary password (8 characters)
            $tempPassword = bin2hex(random_bytes(4));
            $hashedPassword = hashPassword($tempPassword);
            
            // Update the user's password in the database
            $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE user_id = :id");
            $updateStmt->execute([':password' => $hashedPassword, ':id' => $user['user_id']]);
            
            // Send email using centralized sendMail() function
            $htmlBody = "
                <h3>Hello {$user['first_name']},</h3>
                <p>You requested your account details. Since passwords are securely encrypted, we have generated a new temporary password for you.</p>
                <p><strong>Username:</strong> {$user['username']}</p>
                <p><strong>New Password:</strong> {$tempPassword}</p>
                <p>Please <a href='" . BASE_URL_ABS . "/index.php?page=login'>login here</a> and go to your profile to change this password immediately.</p>
                <br>
                <p>Regards,<br>Felege Tibeb Academy</p>
            ";
            $altBody = "Hello {$user['first_name']},\n\nUsername: {$user['username']}\nNew Password: {$tempPassword}\n\nPlease login and change this password immediately.\n\nRegards,\nFelege Tibeb Academy";

            $emailSent = sendMail(
                $user['email'],
                $user['first_name'] . ' ' . $user['last_name'],
                'Your Account Details & Password Reset',
                $htmlBody,
                $altBody
            );

            if ($emailSent) {
                setFlashMessage('success', 'Your username and a new temporary password have been sent to your email.');
            } else {
                setFlashMessage('danger', 'Email could not be sent. Please contact the administrator or configure SMTP settings.');
            }
        } else {
            // For security, it's often better to show the same success message even if email is not found, 
            // to prevent email enumeration. But for this project, showing "not found" is fine.
            setFlashMessage('danger', 'No active account found with that email address.');
        }
    }
    header('Location: ' . buildUrl('auth/forgot-password'));
    exit;
}

$pageTitle = __('forgot_password');
include INCLUDES_PATH . '/header.php';
$flash = getFlashMessage();
?>

<div class="auth-card">
    <div class="auth-logo">
        <i class="bi bi-shield-lock"></i>
        <h4><?= __('forgot_password') ?></h4>
        <p>Enter your email to receive a reset link</p>
    </div>
    
    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="<?= buildUrl('auth/forgot-password') ?>">
        <?= csrfField() ?>
        
        <div class="mb-3">
            <label for="email" class="form-label"><?= __('email') ?></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-send me-2"></i><?= __('reset_password') ?>
        </button>
        
        <div class="text-center mt-3">
            <a href="<?= buildUrl('login') ?>">&larr; <?= __('login') ?></a>
        </div>
    </form>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
