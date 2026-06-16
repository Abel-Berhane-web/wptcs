<?php
/**
 * Admin - Sections Management (with Homeroom Teacher Assignment)
 */
requireRole('admin');

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$pageTitle = __('manage_sections');
$currentYear = getCurrentAcademicYearId();

// ═══ Handle Create/Edit ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
        header('Location: ' . buildUrl('admin/sections'));
        exit;
    }
    
    $sectionId = intval($_POST['section_id'] ?? 0);
    $sectionName = sanitize($_POST['section_name'] ?? '');
    $gradeId = intval($_POST['grade_id'] ?? 0);
    $yearId = intval($_POST['academic_year_id'] ?? 0);
    $homeroomTeacherId = intval($_POST['homeroom_teacher_id'] ?? 0);
    $capacity = intval($_POST['capacity'] ?? 40);
    $status = $_POST['status'] ?? 'active';
    
    if (empty($sectionName) || !$gradeId || !$yearId || !$homeroomTeacherId) {
        setFlashMessage('danger', __('field_required'));
        header('Location: ' . buildUrl('admin/sections', ['action' => $sectionId ? 'edit&id='.$sectionId : 'create']));
        exit;
    }
    
    try {
        if ($sectionId > 0) {
            $stmt = $pdo->prepare("UPDATE sections SET section_name=:name, grade_id=:gid, academic_year_id=:yid, homeroom_teacher_id=:htid, capacity=:cap, status=:st WHERE section_id=:id");
            $stmt->execute([':name'=>$sectionName, ':gid'=>$gradeId, ':yid'=>$yearId, ':htid'=>$homeroomTeacherId, ':cap'=>$capacity, ':st'=>$status, ':id'=>$sectionId]);
            logAudit(getCurrentUserId(), 'update_section', 'sections', $sectionId);
            setFlashMessage('success', __('section_updated'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO sections (section_name, grade_id, academic_year_id, homeroom_teacher_id, capacity, status) VALUES (:name, :gid, :yid, :htid, :cap, :st)");
            $stmt->execute([':name'=>$sectionName, ':gid'=>$gradeId, ':yid'=>$yearId, ':htid'=>$homeroomTeacherId, ':cap'=>$capacity, ':st'=>$status]);
            logAudit(getCurrentUserId(), 'create_section', 'sections', $pdo->lastInsertId());
            setFlashMessage('success', __('section_created'));
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            setFlashMessage('danger', 'This section already exists for the selected grade and academic year.');
        } else {
            setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
        }
    }
    
    header('Location: ' . buildUrl('admin/sections'));
    exit;
}

// ═══ Handle Delete ═══
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Check if students are assigned
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section_id = :id AND status = 'active'");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        setFlashMessage('danger', __('section_has_students'));
    } else {
        $pdo->prepare("DELETE FROM sections WHERE section_id = :id")->execute([':id' => $id]);
        logAudit(getCurrentUserId(), 'delete_section', 'sections', $id);
        setFlashMessage('success', __('section_deleted'));
    }
    header('Location: ' . buildUrl('admin/sections'));
    exit;
}

// Load section for edit
$editSection = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE section_id = :id");
    $stmt->execute([':id' => intval($_GET['id'])]);
    $editSection = $stmt->fetch();
}

// Dropdown data
$grades = $pdo->query("SELECT * FROM grades WHERE status = 'active' ORDER BY grade_order")->fetchAll();
$teachers = $pdo->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY first_name")->fetchAll();
$years = $pdo->query("SELECT * FROM academic_years WHERE status = 'active' ORDER BY year_id DESC")->fetchAll();

// Filters
$filterGrade = $_GET['grade_filter'] ?? '';
$filterYear = $_GET['year_filter'] ?? $currentYear;

$where = "WHERE s.academic_year_id = :year";
$params = [':year' => $filterYear];
if ($filterGrade) {
    $where .= " AND s.grade_id = :grade";
    $params[':grade'] = $filterGrade;
}

$sql = "SELECT s.*, g.grade_name, g.grade_order, CONCAT(t.first_name, ' ', t.last_name) as homeroom_teacher_name, 
        (SELECT COUNT(*) FROM students st WHERE st.section_id = s.section_id AND st.status = 'active') as student_count
        FROM sections s 
        JOIN grades g ON s.grade_id = g.grade_id 
        JOIN users t ON s.homeroom_teacher_id = t.user_id 
        $where ORDER BY g.grade_order, s.section_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sections = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- ═══ Create/Edit Section Form ═══ -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-grid-3x3-gap me-2"></i><?= $editSection ? __('edit_section') : __('create_section') ?></span>
            <a href="<?= buildUrl('admin/sections') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= __('back') ?></a>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= buildUrl('admin/sections') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="section_id" value="<?= $editSection['section_id'] ?? 0 ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?= __('grade') ?> *</label>
                        <select class="form-select" name="grade_id" required>
                            <option value=""><?= __('select_grade') ?></option>
                            <?php foreach ($grades as $g): ?>
                            <option value="<?= $g['grade_id'] ?>" <?= ($editSection['grade_id'] ?? '') == $g['grade_id'] ? 'selected' : '' ?>><?= e($g['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $g['grade_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('section_name') ?> * (A, B, C...)</label>
                        <input type="text" class="form-control" name="section_name" value="<?= e($editSection['section_name'] ?? '') ?>" required maxlength="10" placeholder="A">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('academic_year') ?> *</label>
                        <select class="form-select" name="academic_year_id" required>
                            <?php foreach ($years as $y): ?>
                            <option value="<?= $y['year_id'] ?>" <?= ($editSection['academic_year_id'] ?? $currentYear) == $y['year_id'] ? 'selected' : '' ?>><?= e($y['year_name']) ?> <?= $y['is_current'] ? '★' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-house-heart text-primary me-1"></i><?= __('homeroom_teacher') ?> * <span class="text-danger">(<?= __('required') ?>)</span></label>
                        <select class="form-select" name="homeroom_teacher_id" required>
                            <option value=""><?= __('select_teacher') ?></option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['user_id'] ?>" <?= ($editSection['homeroom_teacher_id'] ?? '') == $t['user_id'] ? 'selected' : '' ?>><?= e($t['first_name'] . ' ' . $t['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><?= __('assign_homeroom_teacher') ?> - Each section must have one homeroom teacher</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?= __('capacity') ?></label>
                        <input type="number" class="form-control" name="capacity" value="<?= e($editSection['capacity'] ?? 40) ?>" min="1" max="100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?= __('status') ?></label>
                        <select class="form-select" name="status">
                            <option value="active" <?= ($editSection['status'] ?? 'active') === 'active' ? 'selected' : '' ?>><?= __('active') ?></option>
                            <option value="inactive" <?= ($editSection['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= __('inactive') ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                    <a href="<?= buildUrl('admin/sections') ?>" class="btn btn-outline-secondary ms-2"><?= __('cancel') ?></a>
                </div>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <!-- ═══ Sections List ═══ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><?= __('sections') ?></h5>
        <a href="<?= buildUrl('admin/sections', ['action' => 'create']) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i><?= __('create_section') ?>
        </a>
    </div>
    
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="admin/sections">
            <div class="form-group">
                <label><?= __('grade') ?></label>
                <select class="form-select form-select-sm" name="grade_filter" onchange="this.form.submit()">
                    <option value=""><?= __('all') ?></option>
                    <?php foreach ($grades as $g): ?>
                    <option value="<?= $g['grade_id'] ?>" <?= $filterGrade == $g['grade_id'] ? 'selected' : '' ?>><?= e($g['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $g['grade_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= __('academic_year') ?></label>
                <select class="form-select form-select-sm" name="year_filter" onchange="this.form.submit()">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y['year_id'] ?>" <?= $filterYear == $y['year_id'] ? 'selected' : '' ?>><?= e($y['year_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= __('grade') ?></th>
                            <th><?= __('section') ?></th>
                            <th><i class="bi bi-house-heart me-1"></i><?= __('homeroom_teacher') ?></th>
                            <th><?= __('student_count') ?></th>
                            <th><?= __('capacity') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $sec): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($sec['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $sec['grade_name']) ?></td>
                            <td><span class="badge bg-primary"><?= e($sec['section_name']) ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person-workspace text-primary"></i>
                                    <span class="fw-semibold"><?= e($sec['homeroom_teacher_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="<?= $sec['student_count'] >= $sec['capacity'] ? 'text-danger fw-bold' : '' ?>">
                                    <?= $sec['student_count'] ?>
                                </span>
                            </td>
                            <td><?= $sec['capacity'] ?></td>
                            <td><span class="badge bg-<?= $sec['status'] === 'active' ? 'success' : 'secondary' ?>"><?= __(e($sec['status'])) ?></span></td>
                            <td>
                                <a href="<?= buildUrl('admin/sections', ['action'=>'edit', 'id'=>$sec['section_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                                <a href="<?= buildUrl('admin/sections', ['action'=>'delete', 'id'=>$sec['section_id']]) ?>" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="<?= __('delete_confirm') ?>"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sections)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted"><?= __('no_data') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
