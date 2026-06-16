<?php
/**
 * Teacher - My Homeroom Classes
 */
requireRole('teacher');

$pdo = getDBConnection();
$teacherId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('homeroom_classes');

$stmt = $pdo->prepare("
    SELECT s.section_id, s.section_name, s.capacity, g.grade_name, g.grade_order,
           (SELECT COUNT(*) FROM students st WHERE st.section_id = s.section_id AND st.status = 'active') as student_count
    FROM sections s JOIN grades g ON s.grade_id = g.grade_id
    WHERE s.homeroom_teacher_id = :tid AND s.academic_year_id = :yid AND s.status = 'active'
    ORDER BY g.grade_order, s.section_name
");
$stmt->execute([':tid' => $teacherId, ':yid' => $currentYear]);
$classes = $stmt->fetchAll();

// If viewing students of a specific section
$viewSection = intval($_GET['view'] ?? 0);
$sectionStudents = [];
$sectionInfo = null;

if ($viewSection && isHomeroomTeacher($teacherId, $viewSection)) {
    $stmt = $pdo->prepare("SELECT s.*, g.grade_name FROM sections s JOIN grades g ON s.grade_id = g.grade_id WHERE s.section_id = :sid");
    $stmt->execute([':sid' => $viewSection]);
    $sectionInfo = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT st.*, CONCAT(p.first_name, ' ', p.last_name) as parent_name, p.phone as parent_phone
        FROM students st LEFT JOIN users p ON st.parent_id = p.user_id
        WHERE st.section_id = :sid AND st.status = 'active'
        ORDER BY st.first_name
    ");
    $stmt->execute([':sid' => $viewSection]);
    $sectionStudents = $stmt->fetchAll();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <?php if ($viewSection && $sectionInfo): ?>
    <!-- Student List for Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0"><?= e($sectionInfo['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $sectionInfo['grade_name']) ?>-<?= e($sectionInfo['section_name']) ?></h5>
            <small class="text-muted"><?= count($sectionStudents) ?> <?= __('students') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= buildUrl('teacher/attendance', ['section_id' => $viewSection]) ?>" class="btn btn-success btn-sm"><i class="bi bi-clipboard-check me-1"></i><?= __('take_attendance') ?></a>
            <a href="<?= buildUrl('teacher/weekly-reports', ['section_id' => $viewSection, 'action' => 'create']) ?>" class="btn btn-info btn-sm text-white"><i class="bi bi-file-earmark-bar-graph me-1"></i><?= __('create_weekly_report') ?></a>
            <a href="<?= buildUrl('teacher/my-homeroom-classes') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= __('back') ?></a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>#</th><th><?= __('student_code') ?></th><th><?= __('student_name') ?></th><th><?= __('gender') ?></th><th><?= __('parent') ?></th><th><?= __('phone') ?></th><th><?= __('actions') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionStudents as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><code><?= e($s['student_code']) ?></code></td>
                            <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td><i class="bi bi-<?= $s['gender'] === 'male' ? 'gender-male text-primary' : 'gender-female text-danger' ?>"></i></td>
                            <td><?= e($s['parent_name'] ?? '-') ?></td>
                            <td><?= e($s['parent_phone'] ?? '-') ?></td>
                            <td>
                                <a href="<?= buildUrl('shared/comments', ['student_id' => $s['student_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="<?= __('comments') ?>"><i class="bi bi-chat-dots"></i></a>
                                <a href="<?= buildUrl('teacher/weekly-reports', ['section_id' => $viewSection, 'action' => 'create', 'student_id' => $s['student_id']]) ?>" class="btn btn-sm btn-outline-info btn-icon" title="<?= __('weekly_report') ?>"><i class="bi bi-file-earmark-bar-graph"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <h5 class="mb-4"><i class="bi bi-house-heart me-2"></i><?= __('homeroom_classes') ?></h5>
    
    <?php if (empty($classes)): ?>
    <div class="empty-state">
        <i class="bi bi-house"></i>
        <h5>No Homeroom Classes Assigned</h5>
        <p>You are not assigned as homeroom teacher for any section.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($classes as $c): ?>
        <div class="col-md-6 col-lg-4">
            <div class="action-card text-center">
                <div class="action-icon bg-primary-soft mx-auto"><i class="bi bi-house-heart"></i></div>
                <h6><?= e($c['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $c['grade_name']) ?>-<?= e($c['section_name']) ?></h6>
                <p><?= $c['student_count'] ?> / <?= $c['capacity'] ?> <?= __('students') ?></p>
                <div class="progress mb-3" style="height:6px;">
                    <div class="progress-bar bg-primary" style="width:<?= min(100, ($c['student_count'] / max(1, $c['capacity'])) * 100) ?>%"></div>
                </div>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?= buildUrl('teacher/my-homeroom-classes', ['view' => $c['section_id']]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye me-1"></i><?= __('view') ?></a>
                    <a href="<?= buildUrl('teacher/attendance', ['section_id' => $c['section_id']]) ?>" class="btn btn-sm btn-success"><i class="bi bi-clipboard-check me-1"></i><?= __('attendance') ?></a>
                    <a href="<?= buildUrl('teacher/weekly-reports', ['section_id' => $c['section_id']]) ?>" class="btn btn-sm btn-info text-white"><i class="bi bi-file-earmark-bar-graph me-1"></i><?= __('reports') ?></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
