<?php
/**
 * Parent - View Homework & Exercises
 * Parents see homework posted for their children's sections
 */
requireRole('parent');

$pdo = getDBConnection();
$parentId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('homework');

$studentId = intval($_GET['student_id'] ?? 0);

// Get parent's children
$stmt = $pdo->prepare("SELECT s.student_id, s.first_name, s.last_name, s.section_id, sec.section_name, g.grade_name
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    LEFT JOIN grades g ON sec.grade_id = g.grade_id
    WHERE s.parent_id = :pid AND s.status = 'active'");
$stmt->execute([':pid' => $parentId]);
$children = $stmt->fetchAll();

// Auto-select first child
if (!$studentId && !empty($children)) {
    $studentId = $children[0]['student_id'];
}

// Get the selected child's section
$selectedChild = null;
$homeworkList = [];
foreach ($children as $c) {
    if ($c['student_id'] == $studentId) {
        $selectedChild = $c;
        break;
    }
}

if ($selectedChild && $selectedChild['section_id']) {
    $stmt = $pdo->prepare("
        SELECT h.*, sub.subject_name, CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
               s.section_name, g.grade_name
        FROM homework h
        JOIN subjects sub ON h.subject_id = sub.subject_id
        JOIN users u ON h.teacher_id = u.user_id
        JOIN sections s ON h.section_id = s.section_id
        JOIN grades g ON s.grade_id = g.grade_id
        WHERE h.section_id = :secid AND h.academic_year_id = :yid
        ORDER BY h.created_at DESC
    ");
    $stmt->execute([':secid' => $selectedChild['section_id'], ':yid' => $currentYear]);
    $homeworkList = $stmt->fetchAll();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i><?= __('homework') ?></h5>
    </div>

    <!-- Child Selector -->
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="parent/homework">
            <div class="form-group">
                <label><?= __('student') ?></label>
                <select class="form-select form-select-sm" name="student_id" onchange="this.form.submit()">
                    <?php foreach ($children as $c): ?>
                    <option value="<?= $c['student_id'] ?>" <?= $studentId == $c['student_id'] ? 'selected' : '' ?>>
                        <?= e($c['first_name'] . ' ' . $c['last_name']) ?> — Grade <?= e($c['grade_name']) ?>-<?= e($c['section_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (empty($homeworkList)): ?>
    <div class="empty-state">
        <i class="bi bi-journal-x"></i>
        <h5>No Homework Posted</h5>
        <p>No homework or exercises have been posted for this class yet.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($homeworkList as $hw): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1"><i class="bi bi-journal-check me-2 text-primary"></i><?= e($hw['title']) ?></h6>
                            <div class="mb-2">
                                <span class="badge bg-info"><?= e(translateSubject($hw['subject_name'])) ?></span>
                                <span class="badge bg-secondary">By: <?= e($hw['teacher_name']) ?></span>
                                <?php if ($hw['week_number']): ?>
                                <span class="badge bg-outline-primary">Week <?= $hw['week_number'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($hw['due_date']): ?>
                        <div class="text-end">
                            <small class="text-muted">Due</small><br>
                            <span class="badge bg-<?= strtotime($hw['due_date']) < time() ? 'danger' : 'warning' ?> fs-6">
                                <?= formatDate($hw['due_date']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2 p-3 bg-light rounded" style="white-space: pre-line;">
                        <?= e($hw['description']) ?>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <i class="bi bi-clock me-1"></i>Posted <?= timeAgo($hw['created_at']) ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
