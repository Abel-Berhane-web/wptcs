<?php
/**
 * Principal Dashboard
 */
requireRole('principal');

$pdo = getDBConnection();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('dashboard');

// Stats
$stats = [];
$stats['students'] = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
$stats['teachers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1")->fetchColumn();
$stats['parents'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND is_active = 1")->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE academic_year_id = :y AND status = 'active'");
$stmt->execute([':y' => $currentYear]);
$stats['sections'] = $stmt->fetchColumn();

// Grade-wise student distribution
$stmt = $pdo->prepare("
    SELECT g.grade_name, g.grade_order, COUNT(st.student_id) as cnt
    FROM grades g
    LEFT JOIN sections s ON g.grade_id = s.grade_id AND s.academic_year_id = :y
    LEFT JOIN students st ON s.section_id = st.section_id AND st.status = 'active'
    WHERE g.status = 'active'
    GROUP BY g.grade_id ORDER BY g.grade_order
");
$stmt->execute([':y' => $currentYear]);
$gradeDistribution = $stmt->fetchAll();

// Today's attendance summary
$stmt = $pdo->query("
    SELECT status, COUNT(*) as cnt
    FROM attendance WHERE attendance_date = CURDATE()
    GROUP BY status
");
$todayAttendance = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
foreach ($stmt->fetchAll() as $a) $todayAttendance[$a['status']] = $a['cnt'];

// Recent announcements
$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
$recentAnnouncements = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="card mb-4" style="background: linear-gradient(135deg, #d97706, #f59e0b); border: none;">
        <div class="card-body py-4">
            <h4 class="text-white mb-1"><?= __('welcome') ?>, <?= e($_SESSION['full_name']) ?>! <span class="wave-icon">👋</span></h4>
            <p class="text-white opacity-75 mb-0"><?= __('school_overview') ?> | <?= __('academic_year') ?>: <?= e(getCurrentAcademicYearName() ?? '') ?></p>
        </div>
    </div>
    
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
        <!-- Today's Attendance -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-clipboard-check me-2"></i><?= __('today') ?> <?= __('attendance') ?></div>
                <div class="card-body">
                    <?php $totalToday = array_sum($todayAttendance) ?: 1; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1"><small><?= __('present') ?></small><small class="fw-bold text-success"><?= $todayAttendance['present'] ?></small></div>
                        <div class="progress"><div class="progress-bar bg-success" style="width:<?= ($todayAttendance['present']/$totalToday)*100 ?>%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1"><small><?= __('absent') ?></small><small class="fw-bold text-danger"><?= $todayAttendance['absent'] ?></small></div>
                        <div class="progress"><div class="progress-bar bg-danger" style="width:<?= ($todayAttendance['absent']/$totalToday)*100 ?>%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1"><small><?= __('late') ?></small><small class="fw-bold text-warning"><?= $todayAttendance['late'] ?></small></div>
                        <div class="progress"><div class="progress-bar bg-warning" style="width:<?= ($todayAttendance['late']/$totalToday)*100 ?>%"></div></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Student Distribution -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Student Distribution</div>
                <div class="card-body">
                    <?php foreach ($gradeDistribution as $gd): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small"><?= e($gd['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $gd['grade_name']) ?></span>
                        <span class="badge bg-primary"><?= $gd['cnt'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-lightning me-2"></i><?= __('quick_actions') ?></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= buildUrl('principal/announcements', ['action'=>'create']) ?>" class="btn btn-outline-primary text-start"><i class="bi bi-megaphone me-2"></i><?= __('post_announcement') ?></a>
                        <a href="<?= buildUrl('principal/reports') ?>" class="btn btn-outline-success text-start"><i class="bi bi-graph-up me-2"></i><?= __('performance_overview') ?></a>
                        <a href="<?= buildUrl('shared/announcements-view') ?>" class="btn btn-outline-info text-start"><i class="bi bi-list-ul me-2"></i><?= __('announcements') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
