<?php
/**
 * Teacher Dashboard
 * Shows BOTH homeroom classes AND subject classes
 */
requireRole('teacher');

$pdo = getDBConnection();
$teacherId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('dashboard');

// ═══ Get Homeroom Classes ═══
$stmt = $pdo->prepare("
    SELECT s.section_id, s.section_name, s.capacity, g.grade_name, g.grade_order,
           (SELECT COUNT(*) FROM students st WHERE st.section_id = s.section_id AND st.status = 'active') as student_count
    FROM sections s
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE s.homeroom_teacher_id = :tid AND s.academic_year_id = :yid AND s.status = 'active'
    ORDER BY g.grade_order, s.section_name
");
$stmt->execute([':tid' => $teacherId, ':yid' => $currentYear]);
$homeroomClasses = $stmt->fetchAll();

// For each homeroom class, check if teacher also teaches a subject there
foreach ($homeroomClasses as &$hc) {
    $stmt = $pdo->prepare("
        SELECT ts.id, sub.subject_name, sub.subject_id
        FROM teacher_subjects ts
        JOIN subjects sub ON ts.subject_id = sub.subject_id
        WHERE ts.teacher_id = :tid AND ts.section_id = :sid AND ts.academic_year_id = :yid
    ");
    $stmt->execute([':tid' => $teacherId, ':sid' => $hc['section_id'], ':yid' => $currentYear]);
    $hc['taught_subjects'] = $stmt->fetchAll();
}
unset($hc);

// ═══ Get Subject Classes ═══
$stmt = $pdo->prepare("
    SELECT ts.id, ts.section_id, ts.subject_id, sub.subject_name, s.section_name, g.grade_name, g.grade_order,
           (SELECT COUNT(*) FROM students st WHERE st.section_id = s.section_id AND st.status = 'active') as student_count,
           CASE WHEN s.homeroom_teacher_id = :tid THEN 1 ELSE 0 END as is_homeroom
    FROM teacher_subjects ts
    JOIN subjects sub ON ts.subject_id = sub.subject_id
    JOIN sections s ON ts.section_id = s.section_id
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE ts.teacher_id = :tid2 AND ts.academic_year_id = :yid AND s.status = 'active'
    ORDER BY sub.subject_name, g.grade_order, s.section_name
");
$stmt->execute([':tid' => $teacherId, ':tid2' => $teacherId, ':yid' => $currentYear]);
$subjectClasses = $stmt->fetchAll();

// ═══ Quick Stats ═══
$totalStudents = 0;
foreach ($homeroomClasses as $hc) $totalStudents += $hc['student_count'];

// Today's attendance status
$todayAttendance = 0;
if (!empty($homeroomClasses)) {
    $sectionIds = array_column($homeroomClasses, 'section_id');
    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT section_id) FROM attendance WHERE section_id IN ($placeholders) AND attendance_date = CURDATE()");
    $stmt->execute($sectionIds);
    $todayAttendance = $stmt->fetchColumn();
}

// Unread comments
$unreadComments = getUnreadCommentsCount($teacherId);

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <!-- Welcome Banner -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #059669, #10b981); border: none;">
        <div class="card-body py-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="text-white mb-1"><?= __('welcome') ?>, <?= e($_SESSION['full_name']) ?>! <span class="wave-icon">👋</span></h4>
                    <p class="text-white opacity-75 mb-0"><?= __('academic_year') ?>: <?= e(getCurrentAcademicYearName() ?? '') ?> | <?= date('l, F j, Y') ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="bi bi-house-heart-fill"></i></div>
                <div class="stat-number"><?= count($homeroomClasses) ?></div>
                <div class="stat-label"><?= __('homeroom_classes') ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
                <div class="stat-number"><?= count($subjectClasses) ?></div>
                <div class="stat-label"><?= __('subject_classes') ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-number"><?= $totalStudents ?></div>
                <div class="stat-label"><?= __('my_students') ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-info">
                <div class="stat-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <div class="stat-number"><?= $unreadComments ?></div>
                <div class="stat-label"><?= __('unread') ?> <?= __('comments') ?></div>
            </div>
        </div>
    </div>
    
    <!-- ═══ MY HOMEROOM CLASSES (Full Access) ═══ -->
    <div class="section-header">
        <i class="bi bi-house-heart"></i>
        <h5><?= __('homeroom_classes') ?></h5>
        <span class="badge bg-primary"><?= __('full_access') ?></span>
    </div>
    
    <?php if (empty($homeroomClasses)): ?>
    <div class="card mb-4">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-info-circle fs-3 mb-2 d-block"></i>
            You are not assigned as homeroom teacher for any section.
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($homeroomClasses as $hc): ?>
        <div class="col-md-6 col-lg-4">
            <div class="action-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-1"><?= e($hc['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $hc['grade_name']) ?>-<?= e($hc['section_name']) ?></h6>
                        <small class="text-muted"><i class="bi bi-people me-1"></i><?= $hc['student_count'] ?> <?= __('students') ?></small>
                    </div>
                    <span class="badge bg-success"><?= __('homeroom_teacher') ?></span>
                </div>
                
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="<?= buildUrl('teacher/attendance', ['section_id' => $hc['section_id']]) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-clipboard-check me-1"></i><?= __('take_attendance') ?>
                    </a>
                    <a href="<?= buildUrl('teacher/weekly-reports', ['section_id' => $hc['section_id']]) ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i><?= __('weekly_report') ?>
                    </a>
                </div>
                
                <?php if (!empty($hc['taught_subjects'])): ?>
                <div class="border-top pt-2 mt-2">
                    <small class="text-success"><i class="bi bi-plus-circle me-1"></i><?= __('also_subject_teacher') ?>:</small>
                    <?php foreach ($hc['taught_subjects'] as $ts): ?>
                    <a href="<?= buildUrl('teacher/enter-marks', ['section_id' => $hc['section_id'], 'subject_id' => $ts['subject_id']]) ?>" class="btn btn-sm btn-outline-success mt-1">
                        <i class="bi bi-pencil-square me-1"></i><?= e(translateSubject($ts['subject_name'])) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <small class="text-muted"><i class="bi bi-dash-circle me-1"></i><?= __('no_subject_taught') ?></small>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- ═══ MY SUBJECT CLASSES (Marks Entry Only) ═══ -->
    <div class="section-header">
        <i class="bi bi-journal-bookmark"></i>
        <h5><?= __('subject_classes') ?></h5>
        <span class="badge bg-warning text-dark"><?= __('marks_entry_only') ?></span>
    </div>
    
    <?php if (empty($subjectClasses)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-info-circle fs-3 mb-2 d-block"></i>
            You are not assigned to teach any subjects.
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($subjectClasses as $sc): ?>
        <div class="col-md-6 col-lg-4">
            <div class="action-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-1"><?= e(translateSubject($sc['subject_name'])) ?></h6>
                        <small class="text-muted">
                            <?= e($sc['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $sc['grade_name']) ?>-<?= e($sc['section_name']) ?>
                            <i class="bi bi-people ms-2 me-1"></i><?= $sc['student_count'] ?>
                        </small>
                    </div>
                    <?php if ($sc['is_homeroom']): ?>
                    <span class="badge bg-success" title="<?= __('homeroom_note') ?>"><i class="bi bi-house-heart"></i></span>
                    <?php endif; ?>
                </div>
                
                <a href="<?= buildUrl('teacher/enter-marks', ['section_id' => $sc['section_id'], 'subject_id' => $sc['subject_id']]) ?>" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-pencil-square me-1"></i><?= __('enter_marks') ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
