<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(SCHOOL_NAME) ?> - Parent Teacher Communication System">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?><?= e(APP_NAME) ?> - <?= e(SCHOOL_NAME) ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Ethiopic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= getCurrentLanguage() === 'am' ? 'lang-am' : 'lang-en' ?>">
<?php if (isLoggedIn()): ?>
<!-- Main Wrapper -->
<div class="app-wrapper">
    <!-- Sidebar -->
    <?php include INCLUDES_PATH . '/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link sidebar-toggle d-md-none" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="d-none d-md-block">
                    <h5 class="page-title mb-0"><?= isset($pageTitle) ? e($pageTitle) : __('dashboard') ?></h5>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Language Switcher -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-globe2"></i>
                        <?= getCurrentLanguage() === 'en' ? 'English' : 'አማርኛ' ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item <?= getCurrentLanguage() === 'en' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?lang=en">
                            <span class="fi fi-us me-2"></span>English
                        </a></li>
                        <li><a class="dropdown-item <?= getCurrentLanguage() === 'am' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?lang=am">
                            <span class="fi fi-et me-2"></span>አማርኛ
                        </a></li>
                    </ul>
                </div>
                
                <!-- Notifications -->
                <?php $notifCount = getUnreadNotificationCount(getCurrentUserId()); ?>
                <div class="dropdown">
                    <button id="notificationBell" class="btn btn-sm btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown" onclick="markNotificationsRead(this)">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifCount > 0): ?>
                        <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $notifCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <script>
                    function markNotificationsRead(btn) {
                        const badge = document.getElementById('notifBadge');
                        if (badge) {
                            fetch('<?= BASE_URL ?>/index.php?action=mark_notifs_read')
                                .then(res => res.json())
                                .then(data => { if (data.success) badge.style.display = 'none'; });
                        }
                    }
                    </script>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 320px;">
                        <h6 class="dropdown-header"><?= __('notifications') ?></h6>
                        <?php
                        $pdo = getDBConnection();
                        $nStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
                        $nStmt->execute([':uid' => getCurrentUserId()]);
                        $notifications = $nStmt->fetchAll();
                        if (empty($notifications)):
                        ?>
                        <div class="px-3 py-2 text-muted text-center"><small><?= __('no_data') ?></small></div>
                        <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                        <a class="dropdown-item d-flex align-items-start py-2 <?= !$notif['is_read'] ? 'bg-light' : '' ?>" href="<?= $notif['link'] ?? '#' ?>">
                            <div class="flex-shrink-0">
                                <i class="bi bi-<?= $notif['type'] === 'success' ? 'check-circle text-success' : ($notif['type'] === 'warning' ? 'exclamation-triangle text-warning' : ($notif['type'] === 'danger' ? 'x-circle text-danger' : 'info-circle text-info')) ?>"></i>
                            </div>
                            <div class="ms-2">
                                <div class="fw-semibold small"><?= e($notif['title']) ?></div>
                                <small class="text-muted"><?= timeAgo($notif['created_at']) ?></small>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-sm d-flex align-items-center gap-2 user-dropdown" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php if ($_SESSION['profile_picture']): ?>
                            <img src="<?= BASE_URL ?>/assets/uploads/<?= e($_SESSION['profile_picture']) ?>" alt="Avatar">
                            <?php else: ?>
                            <div class="avatar-initials"><?= strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="d-none d-md-block text-start">
                            <div class="fw-semibold small"><?= e($_SESSION['full_name']) ?></div>
                            <div class="text-muted" style="font-size: 0.7rem;"><?= __(getCurrentUserRole()) ?></div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= buildUrl('shared/profile') ?>"><i class="bi bi-person me-2"></i><?= __('profile') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= buildUrl('auth/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i><?= __('logout') ?></a></li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="page-content">
            <?php
            // Display flash messages
            $flash = getFlashMessage();
            if ($flash):
            ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'x-circle' : ($flash['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')) ?> me-2"></i>
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
<?php else: ?>
<!-- Public pages (login etc.) -->
<div class="auth-wrapper">
<?php endif; ?>
