<?php
/**
 * Sidebar Navigation
 * WPTCS - Web-based Parent Teacher Communication System
 */
$currentPage = $_GET['page'] ?? '';
$role = getCurrentUserRole();
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex flex-column align-items-center text-center pt-4 pb-3" style="position: relative; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
        <button class="btn btn-link sidebar-close d-md-none position-absolute top-0 end-0 mt-2 me-2" id="sidebarClose" style="color: #fff;">
            <i class="bi bi-x-lg"></i>
        </button>
        <img src="<?= BASE_URL ?>/assets/img/beata_logo.png" alt="School Logo" style="max-height: 70px; margin-bottom: 12px; object-fit: contain;">
        <div style="line-height: 1.3;">
            <div style="font-size: 0.95rem; color: #ffffff; font-weight: 700; font-family: 'Noto Sans Ethiopic', sans-serif;">ፈለገ ጥበብ በዓታ ለማርያም አካዳሚ</div>
            <div style="font-size: 0.75rem; color: rgba(255,255,255,0.7); font-weight: 600; margin-top: 4px;">Felege Tibeb Beata LeMariam Academy</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">

            <?php if ($role === 'admin'): ?>
                <!-- ═══ ADMIN MENU ═══ -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/dashboard' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/dashboard') ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span><?= __('dashboard') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/users' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/users') ?>">
                        <i class="bi bi-people"></i>
                        <span><?= __('manage_users') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/students' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/students') ?>">
                        <i class="bi bi-person-badge"></i>
                        <span><?= __('manage_students') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/sections' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/sections') ?>">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <span><?= __('manage_sections') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/subjects' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/subjects') ?>">
                        <i class="bi bi-book"></i>
                        <span><?= __('manage_subjects') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/grades' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/grades') ?>">
                        <i class="bi bi-list-ol"></i>
                        <span><?= __('manage_grades') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/assign-teachers' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/assign-teachers') ?>">
                        <i class="bi bi-person-check"></i>
                        <span><?= __('assign_teachers') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/academic-years' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/academic-years') ?>">
                        <i class="bi bi-calendar3"></i>
                        <span><?= __('academic_years') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/import-export' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/import-export') ?>">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <span><?= __('import_export') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/promotions' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/promotions') ?>">
                        <i class="bi bi-arrow-up-circle"></i>
                        <span><?= __('promotions') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'shared/announcements-view' ? 'active' : '' ?>"
                        href="<?= buildUrl('shared/announcements-view') ?>">
                        <i class="bi bi-megaphone"></i>
                        <span><?= __('announcements') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin/audit-log' ? 'active' : '' ?>"
                        href="<?= buildUrl('admin/audit-log') ?>">
                        <i class="bi bi-shield-check"></i>
                        <span><?= __('audit_log') ?></span>
                    </a>
                </li>

            <?php elseif ($role === 'principal'): ?>
                <!-- ═══ PRINCIPAL MENU ═══ -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'principal/dashboard' ? 'active' : '' ?>"
                        href="<?= buildUrl('principal/dashboard') ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span><?= __('dashboard') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'principal/announcements' ? 'active' : '' ?>"
                        href="<?= buildUrl('principal/announcements') ?>">
                        <i class="bi bi-megaphone"></i>
                        <span><?= __('announcements') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'principal/reports' ? 'active' : '' ?>"
                        href="<?= buildUrl('principal/reports') ?>">
                        <i class="bi bi-graph-up"></i>
                        <span><?= __('reports') ?></span>
                    </a>
                </li>

            <?php elseif ($role === 'teacher'): ?>
                <!-- ═══ TEACHER MENU ═══ -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'teacher/dashboard' ? 'active' : '' ?>"
                        href="<?= buildUrl('teacher/dashboard') ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span><?= __('dashboard') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'teacher/my-homeroom-classes' ? 'active' : '' ?>"
                        href="<?= buildUrl('teacher/my-homeroom-classes') ?>">
                        <i class="bi bi-house-heart"></i>
                        <span><?= __('homeroom_classes') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'teacher/my-subject-classes' ? 'active' : '' ?>"
                        href="<?= buildUrl('teacher/my-subject-classes') ?>">
                        <i class="bi bi-journal-bookmark"></i>
                        <span><?= __('subject_classes') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'teacher/attendance' ? 'active' : '' ?>"
                        href="<?= buildUrl('teacher/attendance') ?>">
                        <i class="bi bi-clipboard-check"></i>
                        <span><?= __('attendance') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'teacher/enter-marks' ? 'active' : '' ?>"
                        href="<?= buildUrl('teacher/enter-marks') ?>">
                        <i class="bi bi-pencil-square"></i>
                        <span><?= __('enter_marks') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'teacher/weekly-reports' ? 'active' : '' ?>"
                        href="<?= buildUrl('teacher/weekly-reports') ?>">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span><?= __('weekly_reports') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'teacher/homework' ? 'active' : '' ?>"
                        href="<?= buildUrl('teacher/homework') ?>">
                        <i class="bi bi-journal-text"></i>
                        <span><?= __('homework') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'shared/comments' ? 'active' : '' ?>"
                        href="<?= buildUrl('shared/comments') ?>">
                        <i class="bi bi-chat-dots"></i>
                        <span><?= __('comments') ?></span>
                        <?php $unreadComments = getUnreadCommentsCount(getCurrentUserId());
                        if ($unreadComments > 0): ?>
                            <span class="badge bg-danger ms-auto"><?= $unreadComments ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'shared/announcements-view' ? 'active' : '' ?>"
                        href="<?= buildUrl('shared/announcements-view') ?>">
                        <i class="bi bi-megaphone"></i>
                        <span><?= __('announcements') ?></span>
                    </a>
                </li>

            <?php elseif ($role === 'parent'): ?>
                <!-- ═══ PARENT MENU ═══ -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'parent/dashboard' ? 'active' : '' ?>"
                        href="<?= buildUrl('parent/dashboard') ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span><?= __('dashboard') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'parent/my-children' ? 'active' : '' ?>"
                        href="<?= buildUrl('parent/my-children') ?>">
                        <i class="bi bi-people"></i>
                        <span><?= __('my_children') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'parent/view-marks' ? 'active' : '' ?>"
                        href="<?= buildUrl('parent/view-marks') ?>">
                        <i class="bi bi-card-checklist"></i>
                        <span><?= __('view_marks') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'parent/view-reports' ? 'active' : '' ?>"
                        href="<?= buildUrl('parent/view-reports') ?>">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span><?= __('weekly_reports') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'parent/view-attendance' ? 'active' : '' ?>"
                        href="<?= buildUrl('parent/view-attendance') ?>">
                        <i class="bi bi-clipboard-check"></i>
                        <span><?= __('attendance') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'parent/homework' ? 'active' : '' ?>"
                        href="<?= buildUrl('parent/homework') ?>">
                        <i class="bi bi-journal-text"></i>
                        <span><?= __('homework') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'shared/comments' ? 'active' : '' ?>"
                        href="<?= buildUrl('shared/comments') ?>">
                        <i class="bi bi-chat-dots"></i>
                        <span><?= __('comments') ?></span>
                        <?php $unreadComments = getUnreadCommentsCount(getCurrentUserId());
                        if ($unreadComments > 0): ?>
                            <span class="badge bg-danger ms-auto"><?= $unreadComments ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'shared/announcements-view' ? 'active' : '' ?>"
                        href="<?= buildUrl('shared/announcements-view') ?>">
                        <i class="bi bi-megaphone"></i>
                        <span><?= __('announcements') ?></span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Shared Items -->
            <li class="nav-divider"></li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'shared/profile' ? 'active' : '' ?>"
                    href="<?= buildUrl('shared/profile') ?>">
                    <i class="bi bi-person-gear"></i>
                    <span><?= __('profile') ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="<?= buildUrl('auth/logout') ?>">
                    <i class="bi bi-box-arrow-right"></i>
                    <span><?= __('logout') ?></span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <small class="text-muted">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> v<?= APP_VERSION ?></small>
    </div>
</aside>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>