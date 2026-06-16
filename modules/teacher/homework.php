<?php
/**
 * Teacher - Homework & Exercises
 * Post weekly homework/exercises for an entire section at once
 */
requireRole('teacher');

$pdo = getDBConnection();
$teacherId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('homework');

// Handle POST (create new homework)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
        header('Location: ' . buildUrl('teacher/homework'));
        exit;
    }

    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $hwId = intval($_POST['homework_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM homework WHERE homework_id = :id AND teacher_id = :tid");
        $stmt->execute([':id' => $hwId, ':tid' => $teacherId]);
        setFlashMessage('success', 'Homework deleted.');
        header('Location: ' . buildUrl('teacher/homework'));
        exit;
    }

    $sectionId = intval($_POST['section_id'] ?? 0);
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate = $_POST['due_date'] ?? null;
    $weekNumber = intval($_POST['week_number'] ?? date('W'));

    if ($sectionId && $subjectId && $title && $description) {
        try {
            $stmt = $pdo->prepare("INSERT INTO homework (teacher_id, section_id, subject_id, academic_year_id, title, description, due_date, week_number) VALUES (:tid, :secid, :subid, :yid, :title, :desc, :due, :week)");
            $stmt->execute([
                ':tid' => $teacherId,
                ':secid' => $sectionId,
                ':subid' => $subjectId,
                ':yid' => $currentYear,
                ':title' => $title,
                ':desc' => $description,
                ':due' => $dueDate ?: null,
                ':week' => $weekNumber
            ]);

            // Notify all parents of students in that section
            $students = $pdo->prepare("SELECT s.parent_id FROM students s WHERE s.section_id = :secid AND s.status = 'active' AND s.parent_id IS NOT NULL GROUP BY s.parent_id");
            $students->execute([':secid' => $sectionId]);
            $parents = $students->fetchAll();
            foreach ($parents as $p) {
                createNotification($p['parent_id'], 'New Homework', "New homework posted: $title", 'info', buildUrl('parent/homework'));
            }

            logAudit($teacherId, 'post_homework', 'homework', $pdo->lastInsertId());
            setFlashMessage('success', 'Homework posted successfully! All parents have been notified.');
        } catch (PDOException $e) {
            setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
        }
    } else {
        setFlashMessage('warning', 'Please fill in all required fields.');
    }
    header('Location: ' . buildUrl('teacher/homework'));
    exit;
}

// Get teacher's subject+section assignments
$stmt = $pdo->prepare("
    SELECT ts.*, sub.subject_name, s.section_name, g.grade_name
    FROM teacher_subjects ts
    JOIN subjects sub ON ts.subject_id = sub.subject_id
    JOIN sections s ON ts.section_id = s.section_id
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE ts.teacher_id = :tid AND ts.academic_year_id = :yid AND s.status = 'active'
    ORDER BY g.grade_order, s.section_name
");
$stmt->execute([':tid' => $teacherId, ':yid' => $currentYear]);
$assignments = $stmt->fetchAll();

// Get existing homework posted by this teacher
$stmt = $pdo->prepare("
    SELECT h.*, sub.subject_name, s.section_name, g.grade_name
    FROM homework h
    JOIN subjects sub ON h.subject_id = sub.subject_id
    JOIN sections s ON h.section_id = s.section_id
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE h.teacher_id = :tid AND h.academic_year_id = :yid
    ORDER BY h.created_at DESC
");
$stmt->execute([':tid' => $teacherId, ':yid' => $currentYear]);
$homeworkList = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i><?= __('homework') ?></h5>
    </div>

    <div class="row g-4">
        <!-- Post Homework Form -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Post Homework / Exercise</div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('teacher/homework') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create">

                        <div class="mb-3">
                            <label class="form-label"><?= __('subject') ?> / <?= __('section') ?> *</label>
                            <select class="form-select" name="section_subject" id="sectionSubjectSelect" required onchange="splitSectionSubject(this)">
                                <option value=""><?= __('select') ?>...</option>
                                <?php foreach ($assignments as $a): ?>
                                <option value="<?= $a['section_id'] ?>_<?= $a['subject_id'] ?>">
                                    <?= e(translateSubject($a['subject_name'])) ?> — Grade <?= e($a['grade_name']) ?>-<?= e($a['section_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="section_id" id="hw_section_id">
                            <input type="hidden" name="subject_id" id="hw_subject_id">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" placeholder="e.g. Week 12 Math Homework" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Homework & Exercises *</label>
                            <textarea class="form-control" name="description" rows="5" required placeholder="Write all homework details, exercises, and page numbers here..."></textarea>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Week #</label>
                                <input type="number" class="form-control" name="week_number" value="<?= date('W') ?>" min="1" max="52">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Due Date</label>
                                <input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-send me-2"></i>Post to All Students
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Homework List -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list-task me-2"></i>Posted Homework
                    <span class="badge bg-primary ms-2"><?= count($homeworkList) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($homeworkList)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                        No homework posted yet.
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($homeworkList as $hw): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= e($hw['title']) ?></h6>
                                    <div class="mb-1">
                                        <span class="badge bg-info"><?= e(translateSubject($hw['subject_name'])) ?></span>
                                        <span class="badge bg-secondary">Grade <?= e($hw['grade_name']) ?>-<?= e($hw['section_name']) ?></span>
                                        <?php if ($hw['week_number']): ?>
                                        <span class="badge bg-outline-primary">Week <?= $hw['week_number'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1 text-muted small" style="white-space: pre-line;"><?= e($hw['description']) ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?= timeAgo($hw['created_at']) ?>
                                        <?php if ($hw['due_date']): ?>
                                         · <i class="bi bi-calendar-event me-1"></i>Due: <?= formatDate($hw['due_date']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <form method="POST" action="<?= buildUrl('teacher/homework') ?>" class="ms-2" onsubmit="return confirm('Delete this homework?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="homework_id" value="<?= $hw['homework_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function splitSectionSubject(sel) {
    const val = sel.value.split('_');
    document.getElementById('hw_section_id').value = val[0] || '';
    document.getElementById('hw_subject_id').value = val[1] || '';
}
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
