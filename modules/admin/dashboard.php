<?php
/**
 * Admin Dashboard
 */
requireRole('admin');

$pdo = getDBConnection();
$pageTitle = __('dashboard');

// Get statistics
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND is_active = 1");
$stats['teachers'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'parent' AND is_active = 1");
$stats['parents'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
$stats['students'] = $stmt->fetch()['count'];

$currentYear = getCurrentAcademicYearId();
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sections WHERE academic_year_id = :year AND status = 'active'");
$stmt->execute([':year' => $currentYear]);
$stats['sections'] = $stmt->fetch()['count'];

// Recent audit logs
$stmt = $pdo->query("SELECT al.*, u.first_name, u.last_name, u.role FROM audit_log al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.created_at DESC LIMIT 10");
$recentLogs = $stmt->fetchAll();

// Users by role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
$usersByRole = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <!-- Welcome Banner -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #2563eb, #7c3aed); border: none;">
        <div class="card-body py-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="text-white mb-1"><?= __('welcome') ?>, <?= e($_SESSION['full_name']) ?>! <span class="wave-icon">👋</span></h4>
                    <p class="text-white opacity-75 mb-0"><?= e(SCHOOL_NAME) ?> | <?= __('academic_year') ?>: <?= e(getCurrentAcademicYearName() ?? 'N/A') ?></p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <span class="badge bg-white text-primary px-3 py-2 fs-6"><?= __(getCurrentUserRole()) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-number"><?= $stats['students'] ?></div>
                <div class="stat-label"><?= __('total_students') ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
                <div class="stat-number"><?= $stats['teachers'] ?></div>
                <div class="stat-label"><?= __('total_teachers') ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="bi bi-person-hearts"></i></div>
                <div class="stat-number"><?= $stats['parents'] ?></div>
                <div class="stat-label"><?= __('total_parents') ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-info">
                <div class="stat-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                <div class="stat-number"><?= $stats['sections'] ?></div>
                <div class="stat-label"><?= __('total_sections') ?></div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-lightning me-2"></i><?= __('quick_actions') ?>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= buildUrl('admin/users', ['action' => 'create']) ?>" class="btn btn-outline-primary text-start">
                            <i class="bi bi-person-plus me-2"></i><?= __('create_user') ?>
                        </a>
                        <a href="<?= buildUrl('admin/students', ['action' => 'create']) ?>" class="btn btn-outline-success text-start">
                            <i class="bi bi-person-badge me-2"></i><?= __('register_student') ?>
                        </a>
                        <a href="<?= buildUrl('admin/sections', ['action' => 'create']) ?>" class="btn btn-outline-info text-start">
                            <i class="bi bi-grid-3x3-gap me-2"></i><?= __('create_section') ?>
                        </a>
                        <a href="<?= buildUrl('admin/assign-teachers') ?>" class="btn btn-outline-warning text-start">
                            <i class="bi bi-person-check me-2"></i><?= __('assign_teachers') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2"></i><?= __('recent_activities') ?></span>
                    <a href="<?= buildUrl('admin/audit-log') ?>" class="btn btn-sm btn-outline-primary"><?= __('view') ?> <?= __('all') ?></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><?= __('name') ?></th>
                                    <th><?= __('actions') ?></th>
                                    <th><?= __('date') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></div>
                                        <small class="text-muted"><?= e($log['role'] ?? 'system') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?= e($log['action']) ?></span>
                                        <?php if ($log['table_name']): ?>
                                        <small class="text-muted ms-1"><?= e($log['table_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= timeAgo($log['created_at']) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentLogs)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4"><?= __('no_data') ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
