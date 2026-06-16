<?php
/**
 * Teacher - My Subject Classes
 */
requireRole('teacher');

$pdo = getDBConnection();
$teacherId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('subject_classes');

$stmt = $pdo->prepare("
    SELECT ts.*, sub.subject_name, sub.subject_code, s.section_name, g.grade_name, g.grade_order,
           (SELECT COUNT(*) FROM students st WHERE st.section_id = s.section_id AND st.status = 'active') as student_count,
           CASE WHEN s.homeroom_teacher_id = :tid THEN 1 ELSE 0 END as is_homeroom
    FROM teacher_subjects ts
    JOIN subjects sub ON ts.subject_id = sub.subject_id
    JOIN sections s ON ts.section_id = s.section_id
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE ts.teacher_id = :tid2 AND ts.academic_year_id = :yid AND s.status = 'active'
    ORDER BY sub.subject_name, g.grade_order
");
$stmt->execute([':tid' => $teacherId, ':tid2' => $teacherId, ':yid' => $currentYear]);
$classes = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-journal-bookmark me-2"></i><?= __('subject_classes') ?></h5>
    
    <?php if (empty($classes)): ?>
    <div class="empty-state">
        <i class="bi bi-journal-x"></i>
        <h5>No Subject Assignments</h5>
        <p>You are not assigned to teach any subjects. Contact the administrator.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($classes as $c): ?>
        <div class="col-md-6 col-lg-4">
            <div class="action-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="action-icon bg-success-soft"><i class="bi bi-journal-bookmark"></i></div>
                    <?php if ($c['is_homeroom']): ?>
                    <span class="badge bg-success"><i class="bi bi-house-heart me-1"></i>Homeroom</span>
                    <?php endif; ?>
                </div>
                <h6><?= e(translateSubject($c['subject_name'])) ?></h6>
                <p>
                    <?= e($c['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $c['grade_name']) ?>-<?= e($c['section_name']) ?>
                    <br><small class="text-muted"><i class="bi bi-people me-1"></i><?= $c['student_count'] ?> <?= __('students') ?></small>
                </p>
                <a href="<?= buildUrl('teacher/enter-marks', ['section_id' => $c['section_id'], 'subject_id' => $c['subject_id']]) ?>" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-pencil-square me-1"></i><?= __('enter_marks') ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
