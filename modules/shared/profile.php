<?php
/**
 * Shared - Profile Page
 */
requireLogin();

$pdo = getDBConnection();
$userId = getCurrentUserId();
$pageTitle = __('profile');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
        header('Location: ' . buildUrl('shared/profile'));
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        
        // Handle profile picture
        $profilePic = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['profile_picture'], UPLOADS_PATH, ALLOWED_IMAGE_EXTENSIONS);
            if ($result['success']) {
                $profilePic = $result['filename'];
            }
        }
        
        try {
            $sql = "UPDATE users SET first_name=:fn, last_name=:ln, email=:email, phone=:phone";
            $params = [':fn'=>$firstName, ':ln'=>$lastName, ':email'=>$email, ':phone'=>$phone, ':id'=>$userId];
            if ($profilePic) { $sql .= ", profile_picture=:pp"; $params[':pp'] = $profilePic; }
            $sql .= " WHERE user_id=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['full_name'] = "$firstName $lastName";
            $_SESSION['email'] = $email;
            if ($profilePic) $_SESSION['profile_picture'] = $profilePic;
            
            setFlashMessage('success', __('user_updated'));
        } catch (PDOException $e) {
            setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPass, $user['password'])) {
            setFlashMessage('danger', __('login_failed'));
        } elseif ($newPass !== $confirmPass) {
            setFlashMessage('danger', __('password_mismatch'));
        } elseif (strlen($newPass) < 6) {
            setFlashMessage('danger', 'Password must be at least 6 characters.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE user_id = :id");
            $stmt->execute([':pass' => hashPassword($newPass), ':id' => $userId]);
            logAudit($userId, 'change_password', 'users', $userId);
            setFlashMessage('success', __('password_changed'));
        }
    }
    
    header('Location: ' . buildUrl('shared/profile'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="row g-4">
        <!-- Profile Info -->
        <div class="col-lg-4">
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="user-avatar mx-auto mb-3" style="width:80px;height:80px;">
                        <?php if ($user['profile_picture']): ?>
                        <img src="<?= BASE_URL ?>/assets/uploads/<?= e($user['profile_picture']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:16px;">
                        <?php else: ?>
                        <div class="avatar-initials" style="font-size:28px;border-radius:16px;"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <h5><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                    <span class="badge bg-primary mb-3"><?= __(e($user['role'])) ?></span>
                    <div class="text-muted small">
                        <div><i class="bi bi-envelope me-1"></i><?= e($user['email']) ?></div>
                        <div><i class="bi bi-telephone me-1"></i><?= e($user['phone'] ?? '-') ?></div>
                        <div><i class="bi bi-clock me-1"></i><?= __('last_login') ?>: <?= $user['last_login'] ? formatDateTime($user['last_login']) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Profile -->
        <div class="col-lg-8">
            <!-- Update Info -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-person-gear me-2"></i>Edit Profile</div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('shared/profile') ?>" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?= __('first_name') ?></label>
                                <input type="text" class="form-control" name="first_name" value="<?= e($user['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('last_name') ?></label>
                                <input type="text" class="form-control" name="last_name" value="<?= e($user['last_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('email') ?></label>
                                <input type="email" class="form-control" name="email" value="<?= e($user['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('phone') ?></label>
                                <input type="text" class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('profile_picture') ?></label>
                                <input type="file" class="form-control" name="profile_picture" accept="image/*">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i><?= __('update') ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><i class="bi bi-lock me-2"></i><?= __('change_password') ?></div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('shared/profile') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><?= __('current_password') ?></label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?= __('new_password') ?></label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?= __('confirm_password') ?></label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-warning"><i class="bi bi-lock me-2"></i><?= __('change_password') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
