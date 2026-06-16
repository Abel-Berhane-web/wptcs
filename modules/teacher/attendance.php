<?php
/**
 * Teacher - Attendance (Homeroom teacher only)
 */
requireRole('teacher');

$pdo = getDBConnection();
$teacherId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('attendance');

// Handle AJAX attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => __('csrf_error')]);
        exit;
    }
    
    $sectionId = intval($_POST['section_id'] ?? 0);
    $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    $reasons = $_POST['reason'] ?? [];
    
    // Validate homeroom teacher
    if (!isHomeroomTeacher($teacherId, $sectionId)) {
        echo json_encode(['success' => false, 'message' => __('unauthorized')]);
        exit;
    }
    
    $saved = 0;
    foreach ($statuses as $studentId => $status) {
        $studentId = intval($studentId);
        $reason = sanitize($reasons[$studentId] ?? '');
        
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, section_id, attendance_date, status, reason, recorded_by) VALUES (:sid, :secid, :date, :status, :reason, :rby) ON DUPLICATE KEY UPDATE status=VALUES(status), reason=VALUES(reason), recorded_by=VALUES(recorded_by)");
            $stmt->execute([':sid'=>$studentId, ':secid'=>$sectionId, ':date'=>$attendanceDate, ':status'=>$status, ':reason'=>$reason, ':rby'=>$teacherId]);
            $saved++;
        } catch (PDOException $e) {
            // Continue
        }
    }
    
    logAudit($teacherId, 'record_attendance', 'attendance', $sectionId);
    echo json_encode(['success' => true, 'message' => __('attendance_recorded') . " ($saved records)"]);
    exit;
}

// Get homeroom sections
$stmt = $pdo->prepare("
    SELECT s.section_id, s.section_name, g.grade_name, g.grade_order
    FROM sections s JOIN grades g ON s.grade_id = g.grade_id
    WHERE s.homeroom_teacher_id = :tid AND s.academic_year_id = :yid AND s.status = 'active'
    ORDER BY g.grade_order, s.section_name
");
$stmt->execute([':tid' => $teacherId, ':yid' => $currentYear]);
$homeroomSections = $stmt->fetchAll();

$selectedSection = intval($_GET['section_id'] ?? ($homeroomSections[0]['section_id'] ?? 0));
$selectedDate = $_GET['date'] ?? date('Y-m-d');

$students = [];
$existingAttendance = [];

if ($selectedSection) {
    if (!isHomeroomTeacher($teacherId, $selectedSection)) {
        setFlashMessage('danger', __('unauthorized'));
        header('Location: ' . buildUrl('teacher/attendance'));
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT student_id, student_code, first_name, last_name FROM students WHERE section_id = :sid AND status = 'active' ORDER BY first_name, last_name");
    $stmt->execute([':sid' => $selectedSection]);
    $students = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT student_id, status, reason FROM attendance WHERE section_id = :sid AND attendance_date = :date");
    $stmt->execute([':sid' => $selectedSection, ':date' => $selectedDate]);
    foreach ($stmt->fetchAll() as $a) {
        $existingAttendance[$a['student_id']] = $a;
    }
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i><?= __('take_attendance') ?></h5>
    </div>
    
    <?php if (empty($homeroomSections)): ?>
    <div class="empty-state">
        <i class="bi bi-clipboard-x"></i>
        <h5>No Homeroom Classes</h5>
        <p>Only homeroom teachers can take attendance for their sections.</p>
    </div>
    <?php else: ?>
    
    <!-- Filter -->
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="teacher/attendance">
            <div class="form-group">
                <label><?= __('section') ?></label>
                <select class="form-select form-select-sm" name="section_id" onchange="this.form.submit()">
                    <?php foreach ($homeroomSections as $hs): ?>
                    <option value="<?= $hs['section_id'] ?>" <?= $selectedSection == $hs['section_id'] ? 'selected' : '' ?>>
                        <?= e($hs['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $hs['grade_name']) ?>-<?= e($hs['section_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= __('attendance_date') ?></label>
                <input type="date" class="form-control form-control-sm" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
    
    <!-- Quick Set Buttons -->
    <div class="mb-3">
        <span class="me-2 small text-muted">Quick set all:</span>
        <button type="button" class="btn btn-sm btn-success attendance-quick-btn" data-status="present"><i class="bi bi-check-circle me-1"></i><?= __('present') ?></button>
        <button type="button" class="btn btn-sm btn-danger attendance-quick-btn" data-status="absent"><i class="bi bi-x-circle me-1"></i><?= __('absent') ?></button>
    </div>
    
    <!-- Attendance Table -->
    <div class="card">
        <div class="card-body p-0">
            <form id="attendanceForm" action="<?= buildUrl('teacher/attendance') ?>" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="section_id" value="<?= $selectedSection ?>">
                <input type="hidden" name="attendance_date" value="<?= e($selectedDate) ?>">
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= __('student_name') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('reason') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $s): 
                                $existing = $existingAttendance[$s['student_id']] ?? null;
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                    <small class="text-muted"><?= e($s['student_code']) ?></small>
                                </td>
                                <td style="width:180px;">
                                    <select class="form-select form-select-sm attendance-status" name="status[<?= $s['student_id'] ?>]">
                                        <option value="present" <?= ($existing['status'] ?? 'present') === 'present' ? 'selected' : '' ?>><?= __('present') ?></option>
                                        <option value="absent" <?= ($existing['status'] ?? '') === 'absent' ? 'selected' : '' ?>><?= __('absent') ?></option>
                                        <option value="late" <?= ($existing['status'] ?? '') === 'late' ? 'selected' : '' ?>><?= __('late') ?></option>
                                        <option value="excused" <?= ($existing['status'] ?? '') === 'excused' ? 'selected' : '' ?>><?= __('excused') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="reason[<?= $s['student_id'] ?>]" value="<?= e($existing['reason'] ?? '') ?>" placeholder="<?= __('reason') ?> (<?= __('optional') ?>)">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i><?= __('save') ?> <?= __('attendance') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
