<?php
/**
 * Admin - Assign Subject Teachers to Sections
 */
requireRole('admin');

$pdo = getDBConnection();
$pageTitle = __('assign_teachers');
$currentYear = getCurrentAcademicYearId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) { setFlashMessage('danger', __('csrf_error')); header('Location: ' . buildUrl('admin/assign-teachers')); exit; }
    
    $teacherId = intval($_POST['teacher_id'] ?? 0);
    $subjectIds = $_POST['subject_id'] ?? [];
    $sectionIds = $_POST['section_id'] ?? [];
    $yearId = intval($_POST['academic_year_id'] ?? $currentYear);
    
    if ($teacherId && !empty($subjectIds)) {
        $successCount = 0;
        $skipCount = 0;
        $stmt = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id, section_id, academic_year_id) VALUES (:tid, :sid, :secid, :yid)");
        
        for ($i = 0; $i < count($subjectIds); $i++) {
            $sid = intval($subjectIds[$i] ?? 0);
            $secid = intval($sectionIds[$i] ?? 0);
            if (!$sid || !$secid) continue;
            
            try {
                $stmt->execute([':tid'=>$teacherId, ':sid'=>$sid, ':secid'=>$secid, ':yid'=>$yearId]);
                logAudit(getCurrentUserId(), 'assign_teacher_subject', 'teacher_subjects', $pdo->lastInsertId());
                $successCount++;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $skipCount++;
                } else {
                    setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
                }
            }
        }
        
        if ($successCount > 0) {
            setFlashMessage('success', $successCount . ' assignment(s) added successfully.' . ($skipCount > 0 ? " $skipCount already existed." : ''));
        } elseif ($skipCount > 0) {
            setFlashMessage('warning', "All $skipCount assignment(s) already exist.");
        }
    }
    header('Location: ' . buildUrl('admin/assign-teachers'));
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM teacher_subjects WHERE id = :id")->execute([':id' => intval($_GET['delete'])]);
    setFlashMessage('success', 'Assignment removed.');
    header('Location: ' . buildUrl('admin/assign-teachers'));
    exit;
}

// Load data
$teachers = $pdo->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY first_name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects WHERE status = 'active' ORDER BY subject_name")->fetchAll();
$sections = $pdo->prepare("SELECT s.section_id, s.section_name, g.grade_name FROM sections s JOIN grades g ON s.grade_id = g.grade_id WHERE s.academic_year_id = :year AND s.status = 'active' ORDER BY g.grade_order, s.section_name");
$sections->execute([':year' => $currentYear]);
$sectionsList = $sections->fetchAll();

// Current assignments
$assignments = $pdo->prepare("SELECT ts.*, CONCAT(t.first_name, ' ', t.last_name) as teacher_name, sub.subject_name, s.section_name, g.grade_name
    FROM teacher_subjects ts
    JOIN users t ON ts.teacher_id = t.user_id
    JOIN subjects sub ON ts.subject_id = sub.subject_id
    JOIN sections s ON ts.section_id = s.section_id
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE ts.academic_year_id = :year
    ORDER BY t.first_name, g.grade_order, s.section_name");
$assignments->execute([':year' => $currentYear]);
$assignmentsList = $assignments->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="row g-4">
        <!-- Assign Form -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-person-check me-2"></i><?= __('assign_subject_teacher') ?></div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('admin/assign-teachers') ?>" id="assignForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="academic_year_id" value="<?= $currentYear ?>">
                        
                        <!-- Teacher (shared for all rows) -->
                        <div class="mb-3">
                            <label class="form-label"><?= __('teacher') ?> *</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value=""><?= __('select_teacher') ?></option>
                                <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['user_id'] ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Assignment rows container -->
                        <div id="assignmentRows">
                            <div class="assignment-row border rounded p-3 mb-2 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="fw-bold text-primary">Assignment #1</small>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1"><?= __('subject') ?> *</label>
                                    <select class="form-select form-select-sm" name="subject_id[]" required>
                                        <option value=""><?= __('select_subject') ?></option>
                                        <?php foreach ($subjects as $sub): ?>
                                        <option value="<?= $sub['subject_id'] ?>"><?= e(translateSubject($sub['subject_name'])) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small mb-1"><?= __('section') ?> *</label>
                                    <select class="form-select form-select-sm" name="section_id[]" required>
                                        <option value=""><?= __('select_section') ?></option>
                                        <?php foreach ($sectionsList as $sec): ?>
                                        <option value="<?= $sec['section_id'] ?>">Grade <?= e($sec['grade_name']) ?>-<?= e($sec['section_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add More Button -->
                        <button type="button" id="addRowBtn" class="btn btn-sm btn-outline-success w-100 mb-3">
                            <i class="bi bi-plus-circle me-1"></i> Add Another Subject / Section
                        </button>
                        
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- List -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list-check me-2"></i>Current Assignments (<?= e(getCurrentAcademicYearName() ?? '') ?>)
                    <span class="badge bg-primary ms-2"><?= count($assignmentsList) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th><?= __('teacher') ?></th><th><?= __('subject') ?></th><th><?= __('section') ?></th><th><?= __('actions') ?></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignmentsList as $a): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($a['teacher_name']) ?></td>
                                    <td><?= e(translateSubject($a['subject_name'])) ?></td>
                                    <td>Grade <?= e($a['grade_name']) ?>-<?= e($a['section_name']) ?></td>
                                    <td><a href="<?= buildUrl('admin/assign-teachers', ['delete'=>$a['id']]) ?>" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="Remove this assignment?"><i class="bi bi-trash"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($assignmentsList)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted"><?= __('no_data') ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let rowCount = 1;
document.getElementById('addRowBtn').addEventListener('click', function() {
    rowCount++;
    const container = document.getElementById('assignmentRows');
    const firstRow = container.querySelector('.assignment-row');
    const newRow = firstRow.cloneNode(true);
    
    // Update label
    newRow.querySelector('small.fw-bold').textContent = 'Assignment #' + rowCount;
    
    // Reset selects
    newRow.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    
    // Add remove button
    const header = newRow.querySelector('.d-flex');
    if (!header.querySelector('.btn-remove-row')) {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger btn-icon btn-remove-row';
        removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        removeBtn.onclick = function() { newRow.remove(); };
        header.appendChild(removeBtn);
    }
    
    container.appendChild(newRow);
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
