<?php
/**
 * Admin - Student Promotion
 * Pass/Fail → Promote (+1 grade) or Retain (stay)
 */
requireRole('admin');

$pdo = getDBConnection();
$pageTitle = 'Student Promotion';
$currentYear = getCurrentAcademicYearId();
$filterGrade = intval($_GET['grade_id'] ?? 0);
$filterSection = intval($_GET['section_id'] ?? 0);
$action = $_GET['action'] ?? 'review';

// ─── PROCESS PROMOTIONS ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) { setFlashMessage('danger', __('csrf_error')); header('Location: '.buildUrl('admin/promotions')); exit; }
    
    $postAction = $_POST['promotion_action'] ?? '';
    
    // Calculate pass/fail for all students
    if ($postAction === 'calculate') {
        $sectionId = intval($_POST['section_id'] ?? 0);
        $semester = $_POST['semester'] ?? 'both';
        
        $where = "st.status = 'active'";
        $params = [];
        if ($sectionId) { $where .= " AND st.section_id = :secid"; $params[':secid'] = $sectionId; }
        
        $stmt = $pdo->prepare("SELECT st.student_id FROM students st WHERE $where");
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $passed = 0; $failed = 0;
        foreach ($students as $sid) {
            $avg = calculateStudentAverage($sid, $currentYear, $semester === 'both' ? null : $semester);
            $status = ($avg >= PASS_MARK) ? 'passed' : 'failed';
            if ($avg == 0) $status = 'pending'; // No marks = pending
            $pdo->prepare("UPDATE students SET promotion_status = :ps WHERE student_id = :sid")->execute([':ps'=>$status, ':sid'=>$sid]);
            if ($status === 'passed') $passed++;
            elseif ($status === 'failed') $failed++;
        }
        
        logAudit(getCurrentUserId(), 'calculate_results', 'students', $sectionId, null, ['passed'=>$passed, 'failed'=>$failed]);
        setFlashMessage('success', "Results calculated: $passed passed, $failed failed");
        header('Location: '.buildUrl('admin/promotions', ['section_id'=>$sectionId]));
        exit;
    }
    
    // Execute promotions (move passed students to next grade)
    if ($postAction === 'promote') {
        $sectionId = intval($_POST['section_id'] ?? 0);
        $targetYearId = intval($_POST['target_year_id'] ?? 0);
        
        // Get section info
        $stmt = $pdo->prepare("SELECT s.*, g.grade_name, g.grade_id FROM sections s JOIN grades g ON s.grade_id = g.grade_id WHERE s.section_id = :sid");
        $stmt->execute([':sid' => $sectionId]);
        $sectionInfo = $stmt->fetch();
        if (!$sectionInfo) { setFlashMessage('danger', 'Section not found'); header('Location: '.buildUrl('admin/promotions')); exit; }
        
        $nextGrade = getNextGrade($sectionInfo['grade_id']);
        
        // Get passed students
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE section_id = :sid AND promotion_status = 'passed' AND status = 'active'");
        $stmt->execute([':sid' => $sectionId]);
        $passedStudents = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $promoted = 0;
        if ($nextGrade && $targetYearId) {
            // Find or check target section exists in next grade
            $stmt = $pdo->prepare("SELECT section_id FROM sections WHERE grade_id = :gid AND academic_year_id = :yid AND status = 'active' ORDER BY section_name LIMIT 1");
            $stmt->execute([':gid' => $nextGrade['grade_id'], ':yid' => $targetYearId]);
            $targetSectionId = $stmt->fetchColumn();
            
            if ($targetSectionId) {
                foreach ($passedStudents as $sid) {
                    $pdo->prepare("UPDATE students SET section_id = :secid, promotion_status = 'pending' WHERE student_id = :sid")
                        ->execute([':secid'=>$targetSectionId, ':sid'=>$sid]);
                    $promoted++;
                }
            } else {
                setFlashMessage('warning', 'No section found in ' . getGradeDisplayName($nextGrade['grade_name']) . ' for the target year. Create sections first.');
                header('Location: '.buildUrl('admin/promotions', ['section_id'=>$sectionId]));
                exit;
            }
        } elseif (!$nextGrade) {
            // Grade 8 students → graduated
            foreach ($passedStudents as $sid) {
                $pdo->prepare("UPDATE students SET status = 'graduated', promotion_status = 'pending' WHERE student_id = :sid")
                    ->execute([':sid' => $sid]);
                $promoted++;
            }
        }
        
        // Reset failed students' promotion_status back to pending (they stay in current section)
        $pdo->prepare("UPDATE students SET promotion_status = 'pending' WHERE section_id = :sid AND promotion_status = 'failed' AND status = 'active'")
            ->execute([':sid' => $sectionId]);
        
        logAudit(getCurrentUserId(), 'promote_students', 'students', $sectionId, null, ['promoted'=>$promoted]);
        setFlashMessage('success', "$promoted students promoted successfully!");
        header('Location: '.buildUrl('admin/promotions'));
        exit;
    }
}

// Load data
$grades = $pdo->query("SELECT * FROM grades WHERE status = 'active' ORDER BY grade_order")->fetchAll();
$sections = [];
if ($filterGrade || $filterSection) {
    $secSql = "SELECT s.*, g.grade_name FROM sections s JOIN grades g ON s.grade_id = g.grade_id WHERE s.academic_year_id = :y AND s.status = 'active'";
    $secParams = [':y' => $currentYear];
    if ($filterGrade) { $secSql .= " AND s.grade_id = :gid"; $secParams[':gid'] = $filterGrade; }
    $secSql .= " ORDER BY g.grade_order, s.section_name";
    $stmt = $pdo->prepare($secSql);
    $stmt->execute($secParams);
    $sections = $stmt->fetchAll();
}

// Students in selected section
$students = [];
if ($filterSection) {
    $stmt = $pdo->prepare("
        SELECT st.*, sec.section_name, g.grade_name
        FROM students st
        JOIN sections sec ON st.section_id = sec.section_id
        JOIN grades g ON sec.grade_id = g.grade_id
        WHERE st.section_id = :sid AND st.status = 'active'
        ORDER BY st.first_name
    ");
    $stmt->execute([':sid' => $filterSection]);
    $students = $stmt->fetchAll();
    
    // Calculate averages for display
    foreach ($students as &$s) {
        $s['average'] = calculateStudentAverage($s['student_id'], $currentYear);
    }
    unset($s);
}

$academicYears = $pdo->query("SELECT * FROM academic_years WHERE status = 'active' ORDER BY year_id DESC")->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-arrow-up-circle me-2"></i>Student Promotion</h5>
    
    <!-- Filter -->
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="admin/promotions">
            <div class="form-group">
                <label>Grade</label>
                <select class="form-select form-select-sm" name="grade_id" onchange="this.form.submit()">
                    <option value="">-- Select Grade --</option>
                    <?php foreach ($grades as $g): ?>
                    <option value="<?= $g['grade_id'] ?>" <?= $filterGrade == $g['grade_id'] ? 'selected' : '' ?>><?= getGradeDisplayName($g['grade_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($sections)): ?>
            <div class="form-group">
                <label>Section</label>
                <select class="form-select form-select-sm" name="section_id" onchange="this.form.submit()">
                    <option value="">-- Select Section --</option>
                    <?php foreach ($sections as $sec): ?>
                    <option value="<?= $sec['section_id'] ?>" <?= $filterSection == $sec['section_id'] ? 'selected' : '' ?>><?= getGradeDisplayName($sec['grade_name'], $sec['section_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if ($filterSection && !empty($students)): ?>
    <!-- Calculate Results -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <strong>Pass Mark: <?= PASS_MARK ?>%</strong><br>
                <small class="text-muted">Students with average ≥ <?= PASS_MARK ?>% pass, below = fail</small>
            </div>
            <form method="POST" class="d-flex gap-2 align-items-end">
                <?= csrfField() ?>
                <input type="hidden" name="promotion_action" value="calculate">
                <input type="hidden" name="section_id" value="<?= $filterSection ?>">
                <div>
                    <label class="form-label small">Semester</label>
                    <select class="form-select form-select-sm" name="semester">
                        <option value="both">Both Semesters</option>
                        <option value="1">Semester 1 Only</option>
                        <option value="2">Semester 2 Only</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning"><i class="bi bi-calculator me-1"></i>Calculate Pass/Fail</button>
            </form>
        </div>
    </div>
    
    <!-- Students Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-people me-2"></i>Students — <?= getGradeDisplayName($students[0]['grade_name'] ?? '', $students[0]['section_name'] ?? '') ?>
            <span class="badge bg-secondary ms-2"><?= count($students) ?> students</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>#</th><th>Code</th><th>Student Name</th><th>Gender</th><th>Average</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><code><?= e($s['student_code']) ?></code></td>
                            <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td><i class="bi bi-<?= $s['gender']==='male'?'gender-male text-primary':'gender-female text-danger' ?>"></i></td>
                            <td>
                                <span class="fw-bold <?= $s['average'] >= PASS_MARK ? 'text-success' : ($s['average'] > 0 ? 'text-danger' : 'text-muted') ?>">
                                    <?= $s['average'] ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($s['promotion_status'] === 'passed'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Passed</span>
                                <?php elseif ($s['promotion_status'] === 'failed'): ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Summary & Promote Button -->
    <?php 
    $passedCount = count(array_filter($students, fn($s) => $s['promotion_status'] === 'passed'));
    $failedCount = count(array_filter($students, fn($s) => $s['promotion_status'] === 'failed'));
    $pendingCount = count(array_filter($students, fn($s) => $s['promotion_status'] === 'pending'));
    
    // Get current section's grade info
    $stmt = $pdo->prepare("SELECT g.grade_id, g.grade_name FROM sections s JOIN grades g ON s.grade_id = g.grade_id WHERE s.section_id = :sid");
    $stmt->execute([':sid' => $filterSection]);
    $currentGradeInfo = $stmt->fetch();
    $nextGrade = $currentGradeInfo ? getNextGrade($currentGradeInfo['grade_id']) : null;
    ?>
    
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="card text-center p-3" style="border-left:4px solid var(--success)">
                <div class="fw-bold text-success fs-4"><?= $passedCount ?></div>
                <small>Passed (≥ <?= PASS_MARK ?>%)</small>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center p-3" style="border-left:4px solid var(--danger)">
                <div class="fw-bold text-danger fs-4"><?= $failedCount ?></div>
                <small>Failed (&lt; <?= PASS_MARK ?>%)</small>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center p-3" style="border-left:4px solid var(--secondary)">
                <div class="fw-bold text-secondary fs-4"><?= $pendingCount ?></div>
                <small>Pending</small>
            </div>
        </div>
    </div>
    
    <?php if ($passedCount > 0): ?>
    <div class="card border-success">
        <div class="card-body">
            <h6 class="text-success"><i class="bi bi-arrow-up-circle me-2"></i>Promote Passed Students</h6>
            <p class="small text-muted mb-3">
                <?php if ($nextGrade): ?>
                <strong><?= $passedCount ?></strong> students will move from <strong><?= getGradeDisplayName($currentGradeInfo['grade_name']) ?></strong> → <strong><?= getGradeDisplayName($nextGrade['grade_name']) ?></strong>.
                Failed students stay in <strong><?= getGradeDisplayName($currentGradeInfo['grade_name']) ?></strong>.
                <?php else: ?>
                <strong><?= $passedCount ?></strong> students will be marked as <strong>Graduated</strong> (Grade 8 completed).
                <?php endif; ?>
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="promotion_action" value="promote">
                <input type="hidden" name="section_id" value="<?= $filterSection ?>">
                <?php if ($nextGrade): ?>
                <div class="mb-3">
                    <label class="form-label">Target Academic Year</label>
                    <select class="form-select" name="target_year_id" required>
                        <?php foreach ($academicYears as $y): ?>
                        <option value="<?= $y['year_id'] ?>" <?= $y['is_current']?'selected':'' ?>><?= e($y['year_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-success" onclick="return confirm('This will move <?= $passedCount ?> passed students to the next grade. Continue?')">
                    <i class="bi bi-arrow-up-circle me-2"></i><?= $nextGrade ? 'Promote to ' . getGradeDisplayName($nextGrade['grade_name']) : 'Mark as Graduated' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <?php elseif ($filterSection): ?>
    <div class="empty-state">
        <i class="bi bi-people"></i>
        <h5>No students in this section</h5>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-arrow-up-circle"></i>
        <h5>Select a Grade and Section</h5>
        <p>Choose a grade and section to review student results and process promotions.</p>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
