<?php
/**
 * Admin - Students Management
 */
requireRole('admin');

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$pageTitle = __('manage_students');
$currentYear = getCurrentAcademicYearId();

// ═══ Handle Create/Edit ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
        header('Location: ' . buildUrl('admin/students'));
        exit;
    }
    
    $studentId = intval($_POST['student_id'] ?? 0);
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['date_of_birth'] ?? null;
    $parentId = intval($_POST['parent_id'] ?? 0) ?: null;
    $sectionId = intval($_POST['section_id'] ?? 0) ?: null;
    $status = $_POST['status'] ?? 'active';
    $enrollmentDate = $_POST['enrollment_date'] ?? date('Y-m-d');
    
    try {
        // Duplicate Check
        $dupCheck = $pdo->prepare("SELECT student_id FROM students WHERE first_name = :fn AND last_name = :ln AND section_id = :sid AND student_id != :id AND status != 'deleted'");
        $dupCheck->execute([':fn'=>$firstName, ':ln'=>$lastName, ':sid'=>$sectionId, ':id'=>$studentId]);
        if ($dupCheck->fetch()) {
            throw new PDOException("Student {$firstName} {$lastName} already exists in this section.");
        }

        if ($studentId > 0) {
            $stmt = $pdo->prepare("UPDATE students SET first_name=:fn, last_name=:ln, gender=:g, date_of_birth=:dob, parent_id=:pid, section_id=:sid, status=:st WHERE student_id=:id");
            $stmt->execute([':fn'=>$firstName, ':ln'=>$lastName, ':g'=>$gender, ':dob'=>$dob, ':pid'=>$parentId, ':sid'=>$sectionId, ':st'=>$status, ':id'=>$studentId]);
            logAudit(getCurrentUserId(), 'update_student', 'students', $studentId);
            setFlashMessage('success', __('student_updated'));
        } else {
            $studentCode = generateStudentCode(); // Enforce auto-generation
            $stmt = $pdo->prepare("INSERT INTO students (student_code, first_name, last_name, gender, date_of_birth, parent_id, section_id, status, enrollment_date) VALUES (:code, :fn, :ln, :g, :dob, :pid, :sid, :st, :ed)");
            $stmt->execute([':code'=>$studentCode, ':fn'=>$firstName, ':ln'=>$lastName, ':g'=>$gender, ':dob'=>$dob, ':pid'=>$parentId, ':sid'=>$sectionId, ':st'=>$status, ':ed'=>$enrollmentDate]);
            $newId = $pdo->lastInsertId();
            logAudit(getCurrentUserId(), 'create_student', 'students', $newId);
            setFlashMessage('success', __('student_registered'));
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
    }
    
    header('Location: ' . buildUrl('admin/students'));
    exit;
}

// ═══ Handle Delete ═══
if ($action === 'delete' && isset($_GET['id'])) {
    $studentId = intval($_GET['id']);
    $pdo->prepare("DELETE FROM students WHERE student_id = :id")->execute([':id' => $studentId]);
    logAudit(getCurrentUserId(), 'delete_student', 'students', $studentId);
    setFlashMessage('success', __('student_deleted'));
    header('Location: ' . buildUrl('admin/students'));
    exit;
}

// Load student for edit
$editStudent = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :id");
    $stmt->execute([':id' => intval($_GET['id'])]);
    $editStudent = $stmt->fetch();
}

// Load parents for dropdown
$parents = $pdo->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'parent' AND is_active = 1 ORDER BY first_name")->fetchAll();

// Load sections for dropdown
$sections = $pdo->prepare("SELECT s.section_id, s.section_name, g.grade_name FROM sections s JOIN grades g ON s.grade_id = g.grade_id WHERE s.academic_year_id = :year AND s.status = 'active' ORDER BY g.grade_order, s.section_name");
$sections->execute([':year' => $currentYear]);
$sectionsList = $sections->fetchAll();

// ═══ Filters ═══
$filterGrade = $_GET['grade_filter'] ?? '';
$filterSection = $_GET['section_filter'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$page_num = max(1, intval($_GET['p'] ?? 1));

$where = "WHERE 1=1";
$params = [];
if ($filterSection) {
    $where .= " AND st.section_id = :section";
    $params[':section'] = $filterSection;
} elseif ($filterGrade) {
    $where .= " AND g.grade_id = :grade";
    $params[':grade'] = $filterGrade;
}
if ($searchTerm) {
    $where .= " AND (st.first_name LIKE :s1 OR st.last_name LIKE :s2 OR st.student_code LIKE :s3)";
    $params[':s1'] = "%$searchTerm%";
    $params[':s2'] = "%$searchTerm%";
    $params[':s3'] = "%$searchTerm%";
}

$countSql = "SELECT COUNT(*) FROM students st LEFT JOIN sections sec ON st.section_id = sec.section_id LEFT JOIN grades g ON sec.grade_id = g.grade_id $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$pagination = paginate($totalRecords, $page_num);

$sql = "SELECT st.*, sec.section_name, g.grade_name, CONCAT(p.first_name, ' ', p.last_name) as parent_name FROM students st LEFT JOIN sections sec ON st.section_id = sec.section_id LEFT JOIN grades g ON sec.grade_id = g.grade_id LEFT JOIN users p ON st.parent_id = p.user_id $where ORDER BY g.grade_order, sec.section_name, st.first_name LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

$grades = $pdo->query("SELECT * FROM grades WHERE status = 'active' ORDER BY grade_order")->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-badge me-2"></i><?= $editStudent ? __('edit_student') : __('register_student') ?></span>
            <a href="<?= buildUrl('admin/students') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= __('back') ?></a>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= buildUrl('admin/students') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="student_id" value="<?= $editStudent['student_id'] ?? 0 ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?= __('student_code') ?></label>
                        <input type="text" class="form-control bg-light" name="student_code" value="<?= $editStudent ? e($editStudent['student_code']) : '[Auto-Generated]' ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('first_name') ?> *</label>
                        <input type="text" class="form-control" name="first_name" value="<?= e($editStudent['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('last_name') ?> *</label>
                        <input type="text" class="form-control" name="last_name" value="<?= e($editStudent['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('gender') ?> *</label>
                        <select class="form-select" name="gender" required>
                            <option value="male" <?= ($editStudent['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= __('male') ?></option>
                            <option value="female" <?= ($editStudent['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= __('female') ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('date_of_birth') ?></label>
                        <input type="date" class="form-control" name="date_of_birth" value="<?= e($editStudent['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('enrollment_date') ?></label>
                        <input type="date" class="form-control" name="enrollment_date" value="<?= e($editStudent['enrollment_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('assign_parent') ?></label>
                        <input type="text" id="parentSearch" class="form-control form-control-sm mb-1" placeholder="Search parent...">
                        <select class="form-select" name="parent_id" id="parentSelect">
                            <option value=""><?= __('select') ?>...</option>
                            <?php foreach ($parents as $p): ?>
                            <option value="<?= $p['user_id'] ?>" <?= ($editStudent['parent_id'] ?? '') == $p['user_id'] ? 'selected' : '' ?>><?= e($p['first_name'] . ' ' . $p['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('assign_section') ?></label>
                        <select class="form-select" name="section_id">
                            <option value=""><?= __('select') ?>...</option>
                            <?php foreach ($sectionsList as $sec): ?>
                            <option value="<?= $sec['section_id'] ?>" <?= ($editStudent['section_id'] ?? '') == $sec['section_id'] ? 'selected' : '' ?>>Grade <?= e($sec['grade_name']) ?>-<?= e($sec['section_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($editStudent): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('status') ?></label>
                        <select class="form-select" name="status">
                            <option value="active" <?= $editStudent['status'] === 'active' ? 'selected' : '' ?>><?= __('active') ?></option>
                            <option value="inactive" <?= $editStudent['status'] === 'inactive' ? 'selected' : '' ?>><?= __('inactive') ?></option>
                            <option value="transferred" <?= $editStudent['status'] === 'transferred' ? 'selected' : '' ?>><?= __('transferred') ?></option>
                            <option value="graduated" <?= $editStudent['status'] === 'graduated' ? 'selected' : '' ?>><?= __('graduated') ?></option>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="status" value="active">
                    <?php endif; ?>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                    <a href="<?= buildUrl('admin/students') ?>" class="btn btn-outline-secondary ms-2"><?= __('cancel') ?></a>
                </div>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><?= __('student_list') ?> <span class="badge bg-primary ms-2"><?= $totalRecords ?></span></h5>
        <a href="<?= buildUrl('admin/students', ['action' => 'create']) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i><?= __('register_student') ?>
        </a>
    </div>
    
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="admin/students">
            <div class="form-group">
                <label><?= __('grade') ?></label>
                <select class="form-select form-select-sm" name="grade_filter" onchange="this.form.submit()">
                    <option value=""><?= __('all') ?></option>
                    <?php foreach ($grades as $g): ?>
                    <option value="<?= $g['grade_id'] ?>" <?= $filterGrade == $g['grade_id'] ? 'selected' : '' ?>><?= e($g['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $g['grade_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group flex-grow-1">
                <label><?= __('search') ?></label>
                <input type="text" class="form-control form-control-sm" name="search" value="<?= e($searchTerm) ?>" placeholder="Name or student code...">
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= __('student_code') ?></th>
                            <th><?= __('student_name') ?></th>
                            <th><?= __('gender') ?></th>
                            <th><?= __('grade') ?> / <?= __('section') ?></th>
                            <th><?= __('parent') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?= $pagination['offset'] + $i + 1 ?></td>
                            <td><code><?= e($s['student_code']) ?></code></td>
                            <td class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td><i class="bi bi-<?= $s['gender'] === 'male' ? 'gender-male text-primary' : 'gender-female text-danger' ?>"></i></td>
                            <td><?= $s['grade_name'] ? e(($s['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $s['grade_name']) . '-' . $s['section_name']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= $s['parent_name'] ? e($s['parent_name']) : '<span class="text-muted">-</span>' ?></td>
                            <td><span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><?= __(e($s['status'])) ?></span></td>
                            <td>
                                <a href="<?= buildUrl('admin/students', ['action'=>'edit', 'id'=>$s['student_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                                <a href="<?= buildUrl('admin/students', ['action'=>'delete', 'id'=>$s['student_id']]) ?>" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="<?= __('delete_confirm') ?>"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted"><?= __('no_data') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?= renderPagination($pagination, buildUrl('admin/students', ['grade_filter'=>$filterGrade, 'search'=>$searchTerm])) ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('parentSearch');
    const parentSelect = document.getElementById('parentSelect');
    
    if (searchInput && parentSelect) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            let matchCount = 0;
            
            Array.from(parentSelect.options).forEach(option => {
                if (option.value === "") return;
                const text = option.text.toLowerCase();
                const match = text.includes(term);
                option.hidden = !match;
                option.style.display = match ? '' : 'none';
                if (match) matchCount++;
            });
            
            // Automatically expand to show matches when searching
            if (term.length > 0) {
                parentSelect.size = Math.min(matchCount + 1, 6);
                parentSelect.style.position = 'absolute';
                parentSelect.style.zIndex = '1050';
                parentSelect.style.width = searchInput.offsetWidth + 'px';
            } else {
                parentSelect.size = 1;
                parentSelect.style.position = 'static';
            }
        });
        
        // Collapse back to normal when an option is clicked/selected
        parentSelect.addEventListener('change', function() {
            parentSelect.size = 1;
            parentSelect.style.position = 'static';
        });
        
        // Collapse if user clicks anywhere outside the search or select box
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && e.target !== parentSelect) {
                parentSelect.size = 1;
                parentSelect.style.position = 'static';
            }
        });
    }
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
