<?php
/**
 * Admin - Import/Export (CSV for Students, Users, Marks)
 * Download Excel-ready templates, upload filled data
 */
requireRole('admin');

$pdo = getDBConnection();
$pageTitle = 'Import / Export';
$type = $_GET['type'] ?? 'students';

// ─── DOWNLOAD TEMPLATE ───
if (isset($_GET['download'])) {
    $dl = $_GET['download'];
    
    if ($dl === 'students_template') {
        $headers = ['first_name', 'last_name', 'gender', 'date_of_birth', 'grade_name', 'section_name', 'parent_username'];
        $sample = [
            ['Abebe', 'Tadesse', 'male', '2015-03-10', 'KG1', 'A', 'p.abebe'],
            ['Sara', 'Worku', 'female', '2014-07-22', '5', 'A', 'p.tigist'],
        ];
        $csv = generateCSV($headers, $sample);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students_template.csv"');
        echo $csv; exit;
    }
    
    if ($dl === 'users_template') {
        $headers = ['username', 'password', 'email', 'phone', 'first_name', 'last_name', 'role', 'gender'];
        $sample = [
            ['t.teacher1', 'Teacher@123', 'teacher1@school.et', '+251911000001', 'Abebe', 'Kebede', 'teacher', 'male'],
            ['p.parent1', 'Parent@123', 'parent1@gmail.com', '+251912000001', 'Tigist', 'Mengistu', 'parent', 'female'],
        ];
        $csv = generateCSV($headers, $sample);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_template.csv"');
        echo $csv; exit;
    }
    
    if ($dl === 'marks_template') {
        $headers = ['student_code', 'subject_code', 'assessment_type', 'semester', 'score'];
        $sample = [
            ['FTBLM-2025-001', 'ENG', 'Test', '1', '85'],
            ['FTBLM-2025-001', 'MATH', 'Mid Exam', '1', '72'],
        ];
        $csv = generateCSV($headers, $sample);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="marks_template.csv"');
        echo $csv; exit;
    }
    
    // Export current students
    if ($dl === 'students_export') {
        $stmt = $pdo->query("SELECT st.student_code, st.first_name, st.last_name, st.gender, st.date_of_birth, g.grade_name, sec.section_name, u.username as parent_username, st.status FROM students st LEFT JOIN sections sec ON st.section_id = sec.section_id LEFT JOIN grades g ON sec.grade_id = g.grade_id LEFT JOIN users u ON st.parent_id = u.user_id ORDER BY g.grade_order, sec.section_name, st.first_name");
        $rows = [];
        foreach ($stmt->fetchAll() as $s) {
            $rows[] = [$s['student_code'], $s['first_name'], $s['last_name'], $s['gender'], $s['date_of_birth'], $s['grade_name'] ?? '', $s['section_name'] ?? '', $s['parent_username'] ?? '', $s['status']];
        }
        $csv = generateCSV(['student_code','first_name','last_name','gender','date_of_birth','grade_name','section_name','parent_username','status'], $rows);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students_export_'.date('Y-m-d').'.csv"');
        echo $csv; exit;
    }
    
    if ($dl === 'users_export') {
        $stmt = $pdo->query("SELECT username, email, phone, first_name, last_name, role, gender FROM users WHERE is_active = 1 ORDER BY role, first_name");
        $rows = [];
        foreach ($stmt->fetchAll() as $u) {
            $rows[] = [$u['username'], $u['email'], $u['phone'], $u['first_name'], $u['last_name'], $u['role'], $u['gender']];
        }
        $csv = generateCSV(['username','email','phone','first_name','last_name','role','gender'], $rows);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_export_'.date('Y-m-d').'.csv"');
        echo $csv; exit;
    }
}

// ─── PROCESS UPLOAD ───
$importResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) { setFlashMessage('danger', __('csrf_error')); header('Location: ' . buildUrl('admin/import-export', ['type'=>$type])); exit; }
    
    $uploadType = $_POST['upload_type'] ?? '';
    $parsed = parseCSVUpload($_FILES['csv_file'] ?? []);
    
    if (!$parsed['success']) {
        setFlashMessage('danger', $parsed['message']);
        header('Location: ' . buildUrl('admin/import-export', ['type'=>$type]));
        exit;
    }
    
    $rows = $parsed['rows'];
    $success = 0; $errors = [];
    $currentYear = getCurrentAcademicYearId();
    
    if ($uploadType === 'students') {
        foreach ($rows as $i => $row) {
            $lineNum = $i + 2;
            try {
                $fn = trim($row['first_name'] ?? '');
                $ln = trim($row['last_name'] ?? '');
                $gender = trim(strtolower($row['gender'] ?? ''));
                $dob = trim($row['date_of_birth'] ?? '');
                $gradeName = trim($row['grade_name'] ?? '');
                $sectionName = trim($row['section_name'] ?? '');
                $parentUser = trim($row['parent_username'] ?? '');
                
                if (!$fn || !$ln || !in_array($gender, ['male','female'])) {
                    $errors[] = "Row $lineNum: Missing required fields";
                    continue;
                }
                
                // Find parent
                $parentId = null;
                if ($parentUser) {
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :u AND role = 'parent'");
                    $stmt->execute([':u' => $parentUser]);
                    $parentId = $stmt->fetchColumn() ?: null;
                }
                
                // Find section
                $sectionId = null;
                if ($gradeName && $sectionName && $currentYear) {
                    $stmt = $pdo->prepare("SELECT s.section_id FROM sections s JOIN grades g ON s.grade_id = g.grade_id WHERE g.grade_name = :gn AND s.section_name = :sn AND s.academic_year_id = :y AND s.status = 'active'");
                    $stmt->execute([':gn' => $gradeName, ':sn' => $sectionName, ':y' => $currentYear]);
                    $sectionId = $stmt->fetchColumn() ?: null;
                }
                
                $code = generateStudentCode();
                
                // Duplicate check
                $dupCheck = $pdo->prepare("SELECT student_code FROM students WHERE first_name = :fn AND last_name = :ln AND section_id = :sid");
                $dupCheck->execute([':fn'=>$fn, ':ln'=>$ln, ':sid'=>$sectionId]);
                $existing = $dupCheck->fetchColumn();
                
                if ($existing) {
                    $errors[] = "Row $lineNum: Skipped - Student $fn $ln already exists in this section ($existing)";
                    continue;
                }

                $stmt = $pdo->prepare("INSERT INTO students (student_code, first_name, last_name, gender, date_of_birth, parent_id, section_id, enrollment_date) VALUES (:code, :fn, :ln, :gen, :dob, :pid, :sid, CURDATE())");
                $stmt->execute([':code'=>$code, ':fn'=>$fn, ':ln'=>$ln, ':gen'=>$gender, ':dob'=>$dob ?: null, ':pid'=>$parentId, ':sid'=>$sectionId]);
                $success++;
            } catch (PDOException $e) {
                $errors[] = "Row $lineNum: " . $e->getMessage();
            }
        }
    }
    
    elseif ($uploadType === 'users') {
        foreach ($rows as $i => $row) {
            $lineNum = $i + 2;
            try {
                $username = trim($row['username'] ?? '');
                $password = trim($row['password'] ?? '');
                $email = trim($row['email'] ?? '');
                $fn = trim($row['first_name'] ?? '');
                $ln = trim($row['last_name'] ?? '');
                $role = trim(strtolower($row['role'] ?? ''));
                $gender = trim(strtolower($row['gender'] ?? ''));
                $phone = trim($row['phone'] ?? '');
                
                if (!$username || !$password || !$email || !$fn || !$ln || !in_array($role, ['admin','principal','teacher','parent'])) {
                    $errors[] = "Row $lineNum: Missing required fields or invalid role";
                    continue;
                }
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, first_name, last_name, role, gender) VALUES (:u, :p, :e, :ph, :fn, :ln, :r, :g)");
                $stmt->execute([':u'=>$username, ':p'=>hashPassword($password), ':e'=>$email, ':ph'=>$phone, ':fn'=>$fn, ':ln'=>$ln, ':r'=>$role, ':g'=>in_array($gender,['male','female'])?$gender:null]);
                $success++;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errors[] = "Row $lineNum: Skipped - User '$username' or email '$email' already exists.";
                } else {
                    $errors[] = "Row $lineNum: " . $e->getMessage();
                }
            }
        }
    }
    
    elseif ($uploadType === 'marks') {
        $userId = getCurrentUserId();
        foreach ($rows as $i => $row) {
            $lineNum = $i + 2;
            try {
                $studentCode = trim($row['student_code'] ?? '');
                $subjectCode = trim($row['subject_code'] ?? '');
                $assessType = trim($row['assessment_type'] ?? '');
                $semester = trim($row['semester'] ?? '1');
                $score = floatval($row['score'] ?? 0);
                
                if (!$studentCode || !$subjectCode || !$assessType || $score < 0) {
                    $errors[] = "Row $lineNum: Invalid data";
                    continue;
                }
                
                // Lookup IDs
                $student = $pdo->prepare("SELECT student_id, section_id FROM students WHERE student_code = :c");
                $student->execute([':c' => $studentCode]);
                $stData = $student->fetch();
                if (!$stData) { $errors[] = "Row $lineNum: Student $studentCode not found"; continue; }
                
                $subject = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_code = :c");
                $subject->execute([':c' => $subjectCode]);
                $subId = $subject->fetchColumn();
                if (!$subId) { $errors[] = "Row $lineNum: Subject $subjectCode not found"; continue; }
                
                $assess = $pdo->prepare("SELECT type_id, max_score FROM assessment_types WHERE type_name = :n");
                $assess->execute([':n' => $assessType]);
                $atData = $assess->fetch();
                if (!$atData) { $errors[] = "Row $lineNum: Assessment type '$assessType' not found"; continue; }
                
                $atMaxScore = floatval($atData['max_score'] ?? 100);
                if ($score > $atMaxScore) {
                    $errors[] = "Row $lineNum: Score $score exceeds max allowed ($atMaxScore)";
                    continue;
                }

                $stmt = $pdo->prepare("INSERT INTO marks (student_id, subject_id, section_id, assessment_type_id, academic_year_id, semester, score, entered_by) VALUES (:sid, :subid, :secid, :atid, :yid, :sem, :score, :by) ON DUPLICATE KEY UPDATE score = :score2, entered_by = :by2, updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([':sid'=>$stData['student_id'], ':subid'=>$subId, ':secid'=>$stData['section_id'], ':atid'=>$atData['type_id'], ':yid'=>$currentYear, ':sem'=>$semester, ':score'=>$score, ':by'=>$userId, ':score2'=>$score, ':by2'=>$userId]);
                $success++;
            } catch (PDOException $e) {
                $errors[] = "Row $lineNum: " . $e->getMessage();
            }
        }
    }
    
    $importResult = ['success' => $success, 'errors' => $errors, 'total' => count($rows)];
    logAudit(getCurrentUserId(), 'csv_import_' . $uploadType, $uploadType, null, null, ['imported'=>$success,'errors'=>count($errors)]);
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-cloud-arrow-up me-2"></i>Import / Export Data</h5>
    
    <!-- Import Result -->
    <?php if ($importResult): ?>
    <div class="alert alert-<?= empty($importResult['errors']) ? 'success' : 'warning' ?> alert-dismissible fade show">
        <strong>Import Complete!</strong> <?= $importResult['success'] ?> of <?= $importResult['total'] ?> records imported successfully.
        <?php if (!empty($importResult['errors'])): ?>
        <hr><strong>Errors (<?= count($importResult['errors']) ?>):</strong>
        <ul class="mb-0 small"><?php foreach (array_slice($importResult['errors'], 0, 10) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        <?php if (count($importResult['errors']) > 10): ?><li>... and <?= count($importResult['errors'])-10 ?> more</li><?php endif; ?></ul>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><a class="nav-link <?= $type==='students'?'active':'' ?>" href="<?= buildUrl('admin/import-export', ['type'=>'students']) ?>"><i class="bi bi-person-badge me-1"></i>Students</a></li>
        <li class="nav-item"><a class="nav-link <?= $type==='users'?'active':'' ?>" href="<?= buildUrl('admin/import-export', ['type'=>'users']) ?>"><i class="bi bi-people me-1"></i>Users</a></li>
        <li class="nav-item"><a class="nav-link <?= $type==='marks'?'active':'' ?>" href="<?= buildUrl('admin/import-export', ['type'=>'marks']) ?>"><i class="bi bi-card-checklist me-1"></i>Marks</a></li>
    </ul>
    
    <div class="row g-4">
        <?php if ($type === 'students'): ?>
        <!-- STUDENTS -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white"><i class="bi bi-download me-2"></i>Download</div>
                <div class="card-body">
                    <p>Download the CSV template, fill it in Excel, and upload.</p>
                    <div class="d-grid gap-2">
                        <a href="<?= buildUrl('admin/import-export', ['download'=>'students_template']) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Download Blank Template</a>
                        <a href="<?= buildUrl('admin/import-export', ['download'=>'students_export']) ?>" class="btn btn-outline-primary"><i class="bi bi-cloud-download me-2"></i>Export Current Students</a>
                    </div>
                    <hr>
                    <h6>Template Columns:</h6>
                    <table class="table table-sm small">
                        <tr><td class="fw-bold">first_name</td><td>Student first name (required)</td></tr>
                        <tr><td class="fw-bold">last_name</td><td>Student last name (required)</td></tr>
                        <tr><td class="fw-bold">gender</td><td>male or female (required)</td></tr>
                        <tr><td class="fw-bold">date_of_birth</td><td>YYYY-MM-DD format</td></tr>
                        <tr><td class="fw-bold">grade_name</td><td>KG1, KG2, KG3, 1, 2, ... 8</td></tr>
                        <tr><td class="fw-bold">section_name</td><td>A, B, C, etc.</td></tr>
                        <tr><td class="fw-bold">parent_username</td><td>Parent's login username</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white"><i class="bi bi-upload me-2"></i>Upload Students</div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('admin/import-export', ['type'=>'students']) ?>" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="upload_type" value="students">
                        <div class="mb-3">
                            <label class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv,.txt" required>
                            <small class="text-muted">Max 5MB. UTF-8 CSV with headers.</small>
                        </div>
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle me-1"></i>Student codes are auto-generated. Grade + section must already exist.
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-arrow-up me-2"></i>Import Students</button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php elseif ($type === 'users'): ?>
        <!-- USERS -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white"><i class="bi bi-download me-2"></i>Download</div>
                <div class="card-body">
                    <p>Download the CSV template to bulk-create user accounts.</p>
                    <div class="d-grid gap-2">
                        <a href="<?= buildUrl('admin/import-export', ['download'=>'users_template']) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Download Blank Template</a>
                        <a href="<?= buildUrl('admin/import-export', ['download'=>'users_export']) ?>" class="btn btn-outline-primary"><i class="bi bi-cloud-download me-2"></i>Export Current Users</a>
                    </div>
                    <hr>
                    <h6>Template Columns:</h6>
                    <table class="table table-sm small">
                        <tr><td class="fw-bold">username</td><td>Unique login username (required)</td></tr>
                        <tr><td class="fw-bold">password</td><td>Initial password (required, will be hashed)</td></tr>
                        <tr><td class="fw-bold">email</td><td>Unique email (required)</td></tr>
                        <tr><td class="fw-bold">phone</td><td>Phone number</td></tr>
                        <tr><td class="fw-bold">first_name</td><td>First name (required)</td></tr>
                        <tr><td class="fw-bold">last_name</td><td>Last name (required)</td></tr>
                        <tr><td class="fw-bold">role</td><td>admin, principal, teacher, or parent</td></tr>
                        <tr><td class="fw-bold">gender</td><td>male or female</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white"><i class="bi bi-upload me-2"></i>Upload Users</div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('admin/import-export', ['type'=>'users']) ?>" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="upload_type" value="users">
                        <div class="mb-3">
                            <label class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv,.txt" required>
                        </div>
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>Passwords in the CSV are auto-hashed. Duplicates are skipped.
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-arrow-up me-2"></i>Import Users</button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- MARKS -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white"><i class="bi bi-download me-2"></i>Download</div>
                <div class="card-body">
                    <p>Download the CSV template to bulk-enter marks.</p>
                    <div class="d-grid gap-2">
                        <a href="<?= buildUrl('admin/import-export', ['download'=>'marks_template']) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Download Blank Template</a>
                    </div>
                    <hr>
                    <h6>Template Columns:</h6>
                    <table class="table table-sm small">
                        <tr><td class="fw-bold">student_code</td><td>e.g., FTBLM-2025-001</td></tr>
                        <tr><td class="fw-bold">subject_code</td><td>ENG, MATH, AMH, etc.</td></tr>
                        <tr><td class="fw-bold">assessment_type</td><td>Test, Mid Exam, Group Work, Final Exam</td></tr>
                        <tr><td class="fw-bold">semester</td><td>1 or 2</td></tr>
                        <tr><td class="fw-bold">score</td><td>0 to 100</td></tr>
                    </table>
                    <hr>
                    <h6>Available Subjects:</h6>
                    <div class="d-flex flex-wrap gap-1">
                    <?php $subs = $pdo->query("SELECT subject_code, subject_name FROM subjects WHERE status='active'")->fetchAll(); foreach ($subs as $s): ?>
                        <span class="badge bg-light text-dark"><?= e($s['subject_code']) ?> = <?= e(translateSubject($s['subject_name'])) ?></span>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white"><i class="bi bi-upload me-2"></i>Upload Marks</div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('admin/import-export', ['type'=>'marks']) ?>" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="upload_type" value="marks">
                        <div class="mb-3">
                            <label class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv,.txt" required>
                        </div>
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle me-1"></i>Marks are entered for the current academic year. Existing marks are updated.
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-arrow-up me-2"></i>Import Marks</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
