<?php
/**
 * Principal - School Reports
 */
requireRole('principal');

$pdo = getDBConnection();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('reports');

// Performance by grade
$stmt = $pdo->prepare("
    SELECT g.grade_name, g.grade_order, ROUND(AVG(m.score), 1) as avg_score, COUNT(DISTINCT m.student_id) as student_count
    FROM marks m
    JOIN sections s ON m.section_id = s.section_id
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE m.academic_year_id = :yid
    GROUP BY g.grade_id ORDER BY g.grade_order
");
$stmt->execute([':yid' => $currentYear]);
$gradePerformance = $stmt->fetchAll();

// Performance by subject
$stmt = $pdo->prepare("
    SELECT sub.subject_name, ROUND(AVG(m.score), 1) as avg_score
    FROM marks m JOIN subjects sub ON m.subject_id = sub.subject_id
    WHERE m.academic_year_id = :yid
    GROUP BY sub.subject_id ORDER BY avg_score DESC
");
$stmt->execute([':yid' => $currentYear]);
$subjectPerformance = $stmt->fetchAll();

// Teacher summary
$stmt = $pdo->prepare("
    SELECT CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           (SELECT COUNT(DISTINCT section_id) FROM sections WHERE homeroom_teacher_id = u.user_id AND academic_year_id = :y1 AND status='active') as homeroom_count,
           (SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = u.user_id AND academic_year_id = :y2) as subject_count
    FROM users u WHERE u.role = 'teacher' AND u.is_active = 1 ORDER BY u.first_name
");
$stmt->execute([':y1' => $currentYear, ':y2' => $currentYear]);
$teacherSummary = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-graph-up me-2"></i><?= __('performance_overview') ?></h5>
    
    <div class="row g-4">
        <!-- Grade Performance -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><?= __('performance_overview') ?> by <?= __('grade') ?></div>
                <div class="card-body">
                    <?php foreach ($gradePerformance as $gp): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= e($gp['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $gp['grade_name']) ?></span>
                            <span class="fw-bold <?= $gp['avg_score'] >= 50 ? 'text-success' : 'text-danger' ?>"><?= $gp['avg_score'] ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-<?= $gp['avg_score'] >= 70 ? 'success' : ($gp['avg_score'] >= 50 ? 'warning' : 'danger') ?>" style="width:<?= $gp['avg_score'] ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $gp['student_count'] ?> students</small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Subject Performance -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><?= __('performance_overview') ?> by <?= __('subject') ?></div>
                <div class="card-body">
                    <?php foreach ($subjectPerformance as $sp): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= e(translateSubject($sp['subject_name'])) ?></span>
                            <span class="fw-bold"><?= $sp['avg_score'] ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-primary" style="width:<?= $sp['avg_score'] ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Teacher Summary -->
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-person-workspace me-2"></i>Teacher Assignments Overview</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th><?= __('teacher') ?></th><th>Homeroom Classes</th><th>Subject Assignments</th></tr></thead>
                            <tbody>
                                <?php foreach ($teacherSummary as $ts): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($ts['teacher_name']) ?></td>
                                    <td><span class="badge bg-primary"><?= $ts['homeroom_count'] ?></span></td>
                                    <td><span class="badge bg-success"><?= $ts['subject_count'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
