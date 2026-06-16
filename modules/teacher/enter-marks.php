<?php
/**
 * Teacher - Enter/Edit Marks
 * Only for subjects the teacher is assigned to teach
 */
requireRole('teacher');

$pdo = getDBConnection();
$teacherId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('enter_marks');

// Handle AJAX marks submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => __('csrf_error')]);
        exit;
    }
    
    $sectionId = intval($_POST['section_id'] ?? 0);
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $assessmentTypeId = intval($_POST['assessment_type_id'] ?? 0);
    $semester = $_POST['semester'] ?? '1';
    $scores = $_POST['scores'] ?? [];
    
    // Validate teacher is assigned to this subject+section
    if (!isSubjectTeacher($teacherId, $subjectId, $sectionId)) {
        echo json_encode(['success' => false, 'message' => __('unauthorized')]);
        exit;
    }
    
    $saved = 0;
    $errors = [];
    
    $stmt = $pdo->prepare("SELECT max_score FROM assessment_types WHERE type_id = :id");
    $stmt->execute([':id' => $assessmentTypeId]);
    $maxScoreLimit = floatval($stmt->fetchColumn() ?: 100);
    
    foreach ($scores as $studentId => $score) {
        $studentId = intval($studentId);
        $score = floatval($score);
        
        if ($score < MIN_SCORE || $score > $maxScoreLimit) {
            $errors[] = __('invalid_score') . " (Max: $maxScoreLimit) for Student #$studentId";
            continue;
        }
        
        try {
            // Check if mark already exists
            $stmt = $pdo->prepare("SELECT mark_id FROM marks WHERE student_id=:sid AND subject_id=:subid AND assessment_type_id=:atid AND academic_year_id=:yid AND semester=:sem");
            $stmt->execute([':sid'=>$studentId, ':subid'=>$subjectId, ':atid'=>$assessmentTypeId, ':yid'=>$currentYear, ':sem'=>$semester]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE marks SET score=:score, entered_by=:eby WHERE mark_id=:mid");
                $stmt->execute([':score'=>$score, ':eby'=>$teacherId, ':mid'=>$existing['mark_id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO marks (student_id, subject_id, section_id, assessment_type_id, academic_year_id, semester, score, entered_by) VALUES (:sid, :subid, :secid, :atid, :yid, :sem, :score, :eby)");
                $stmt->execute([':sid'=>$studentId, ':subid'=>$subjectId, ':secid'=>$sectionId, ':atid'=>$assessmentTypeId, ':yid'=>$currentYear, ':sem'=>$semester, ':score'=>$score, ':eby'=>$teacherId]);
            }
            $saved++;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    logAudit($teacherId, 'enter_marks', 'marks', $sectionId, null, ['subject_id'=>$subjectId, 'count'=>$saved]);
    
    if ($saved > 0) {
        echo json_encode(['success' => true, 'message' => __('marks_entered') . " ($saved records)"]);
    } else {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    }
    exit;
}

// ═══ Get teacher's subject assignments ═══
$stmt = $pdo->prepare("
    SELECT ts.*, sub.subject_name, s.section_name, g.grade_name, g.grade_order
    FROM teacher_subjects ts
    JOIN subjects sub ON ts.subject_id = sub.subject_id
    JOIN sections s ON ts.section_id = s.section_id
    JOIN grades g ON s.grade_id = g.grade_id
    WHERE ts.teacher_id = :tid AND ts.academic_year_id = :yid AND s.status = 'active'
    ORDER BY sub.subject_name, g.grade_order
");
$stmt->execute([':tid' => $teacherId, ':yid' => $currentYear]);
$assignments = $stmt->fetchAll();

// Selected section/subject
$selectedSection = intval($_GET['section_id'] ?? 0);
$selectedSubject = intval($_GET['subject_id'] ?? 0);
$selectedAssessment = intval($_GET['assessment_type_id'] ?? 0);
$selectedSemester = $_GET['semester'] ?? '1';

$students = [];
$assessmentTypes = $pdo->query("SELECT * FROM assessment_types WHERE status = 'active' ORDER BY type_id")->fetchAll();
$existingMarks = [];

if ($selectedSection && $selectedSubject && $selectedAssessment) {
    // Validate access
    if (!isSubjectTeacher($teacherId, $selectedSubject, $selectedSection)) {
        setFlashMessage('danger', __('unauthorized'));
        header('Location: ' . buildUrl('teacher/enter-marks'));
        exit;
    }
    
    $selectedAssessmentMax = 100;
    foreach ($assessmentTypes as $at) {
        if ($at['type_id'] == $selectedAssessment) {
            $selectedAssessmentMax = floatval($at['max_score']);
            break;
        }
    }
    
    // Get students
    $stmt = $pdo->prepare("SELECT student_id, student_code, first_name, last_name FROM students WHERE section_id = :sid AND status = 'active' ORDER BY first_name, last_name");
    $stmt->execute([':sid' => $selectedSection]);
    $students = $stmt->fetchAll();
    
    // Get existing marks
    $stmt = $pdo->prepare("SELECT student_id, score FROM marks WHERE subject_id=:subid AND section_id=:secid AND assessment_type_id=:atid AND academic_year_id=:yid AND semester=:sem");
    $stmt->execute([':subid'=>$selectedSubject, ':secid'=>$selectedSection, ':atid'=>$selectedAssessment, ':yid'=>$currentYear, ':sem'=>$selectedSemester]);
    foreach ($stmt->fetchAll() as $m) {
        $existingMarks[$m['student_id']] = $m;
    }
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i><?= __('enter_marks') ?></h5>
    </div>
    
    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="teacher/enter-marks">
            <div class="form-group">
                <label><?= __('subject') ?> / <?= __('section') ?></label>
                <select class="form-select form-select-sm" name="section_subject" onchange="submitMarksFilter(this)">
                    <option value=""><?= __('select') ?>...</option>
                    <?php foreach ($assignments as $a): ?>
                    <option value="<?= $a['section_id'] ?>_<?= $a['subject_id'] ?>" <?= ($selectedSection == $a['section_id'] && $selectedSubject == $a['subject_id']) ? 'selected' : '' ?>>
                        <?= e(translateSubject($a['subject_name'])) ?> — Grade <?= e($a['grade_name']) ?>-<?= e($a['section_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= __('assessment_type') ?></label>
                <select class="form-select form-select-sm" name="assessment_type_id" onchange="this.form.submit()">
                    <option value=""><?= __('select') ?>...</option>
                    <?php foreach ($assessmentTypes as $at): ?>
                    <option value="<?= $at['type_id'] ?>" <?= $selectedAssessment == $at['type_id'] ? 'selected' : '' ?>><?= e($at['type_name']) ?> (<?= intval($at['max_score']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= __('semester') ?></label>
                <select class="form-select form-select-sm" name="semester" onchange="this.form.submit()">
                    <option value="1" <?= $selectedSemester === '1' ? 'selected' : '' ?>><?= __('semester_1') ?></option>
                    <option value="2" <?= $selectedSemester === '2' ? 'selected' : '' ?>><?= __('semester_2') ?></option>
                </select>
            </div>
        </form>
    </div>
    
    <!-- Hidden inputs to pass section_id/subject_id from combined select -->
    <script>
    function submitMarksFilter(sel) {
        const val = sel.value.split('_');
        if (val.length === 2) {
            const form = sel.form;
            let si = form.querySelector('input[name="section_id"]');
            let sbi = form.querySelector('input[name="subject_id"]');
            if (!si) { si = document.createElement('input'); si.type='hidden'; si.name='section_id'; form.appendChild(si); }
            if (!sbi) { sbi = document.createElement('input'); sbi.type='hidden'; sbi.name='subject_id'; form.appendChild(sbi); }
            si.value = val[0];
            sbi.value = val[1];
        } else {
            const form = sel.form;
            let si = form.querySelector('input[name="section_id"]');
            let sbi = form.querySelector('input[name="subject_id"]');
            if(si) si.value = '';
            if(sbi) sbi.value = '';
        }
        sel.form.submit();
    }
    
    // Set on load
    (function(){
        const sel = document.querySelector('select[name="section_subject"]');
        if (sel && sel.value) {
            const val = sel.value.split('_');
            const form = sel.form;
            let si = document.createElement('input'); si.type='hidden'; si.name='section_id'; si.value=val[0]; form.appendChild(si);
            let sbi = document.createElement('input'); sbi.type='hidden'; sbi.name='subject_id'; sbi.value=val[1]; form.appendChild(sbi);
        }
    })();
    </script>
    
    <?php if (!empty($students) && $selectedAssessment): ?>
    <!-- Marks Entry Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-table me-2"></i><?= __('class_marksheet') ?></span>
            <span class="text-muted"><?= count($students) ?> <?= __('students') ?></span>
        </div>
        <div class="card-body p-0">
            <form id="marksForm" action="<?= buildUrl('teacher/enter-marks') ?>" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="section_id" value="<?= $selectedSection ?>">
                <input type="hidden" name="subject_id" value="<?= $selectedSubject ?>">
                <input type="hidden" name="assessment_type_id" value="<?= $selectedAssessment ?>">
                <input type="hidden" name="semester" value="<?= e($selectedSemester) ?>">
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= __('student_code') ?></th>
                                <th><?= __('student_name') ?></th>
                                <th><?= __('score') ?> (0-<?= $selectedAssessmentMax ?>)</th>
                                <th><?= __('status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $s): 
                                $existing = $existingMarks[$s['student_id']] ?? null;
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><code><?= e($s['student_code']) ?></code></td>
                                <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                <td style="width:200px;">
                                    <input type="number" class="form-control form-control-sm" 
                                           name="scores[<?= $s['student_id'] ?>]" 
                                           value="<?= $existing ? $existing['score'] : '' ?>"
                                           min="0" max="<?= $selectedAssessmentMax ?>" step="0.5"
                                           data-max-score="<?= $selectedAssessmentMax ?>"
                                           placeholder="0-<?= $selectedAssessmentMax ?>">
                                </td>
                                <td>
                                    <?php if ($existing): ?>
                                    <span class="badge bg-success"><i class="bi bi-check me-1"></i>Saved</span>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark">New</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i><?= __('save') ?> <?= __('marks') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php elseif ($selectedSection && $selectedSubject && !$selectedAssessment): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Please select an assessment type to continue.</div>
    <?php elseif (empty($assignments)): ?>
    <div class="empty-state">
        <i class="bi bi-journal-x"></i>
        <h5>No Subject Assignments</h5>
        <p>You are not assigned to teach any subjects. Contact the administrator.</p>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
