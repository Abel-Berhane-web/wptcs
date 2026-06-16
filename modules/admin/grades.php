<?php
/**
 * Admin - Manage Grades (KG1, KG2, KG3, 1-8)
 */
requireRole('admin');

$pdo = getDBConnection();
$pageTitle = __('manage_grades');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) { setFlashMessage('danger', __('csrf_error')); header('Location: ' . buildUrl('admin/grades')); exit; }

    $action = $_POST['form_action'] ?? 'create';
    $gradeName = sanitize($_POST['grade_name'] ?? '');
    $gradeOrder = intval($_POST['grade_order'] ?? 0);
    $gradeId = intval($_POST['grade_id'] ?? 0);

    try {
        if ($action === 'edit' && $gradeId) {
            $stmt = $pdo->prepare("UPDATE grades SET grade_name=:name, grade_order=:ord WHERE grade_id=:id");
            $stmt->execute([':name'=>$gradeName, ':ord'=>$gradeOrder, ':id'=>$gradeId]);
            setFlashMessage('success', 'Grade updated successfully');
        } else {
            $stmt = $pdo->prepare("INSERT INTO grades (grade_name, grade_order) VALUES (:name, :ord)");
            $stmt->execute([':name'=>$gradeName, ':ord'=>$gradeOrder]);
            setFlashMessage('success', 'Grade added successfully');
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Error: ' . ($e->getCode() == 23000 ? 'Grade name already exists' : $e->getMessage()));
    }
    header('Location: ' . buildUrl('admin/grades'));
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $gid = intval($_GET['delete']);
    // Check if used in sections
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE grade_id = :gid");
    $stmt->execute([':gid' => $gid]);
    if ($stmt->fetchColumn() > 0) {
        setFlashMessage('danger', 'Cannot delete: grade has sections assigned');
    } else {
        $pdo->prepare("DELETE FROM grades WHERE grade_id = :id")->execute([':id' => $gid]);
        setFlashMessage('success', 'Grade deleted');
    }
    header('Location: ' . buildUrl('admin/grades'));
    exit;
}

$editGrade = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM grades WHERE grade_id = :id");
    $stmt->execute([':id' => intval($_GET['edit'])]);
    $editGrade = $stmt->fetch();
}

$grades = $pdo->query("SELECT g.*, (SELECT COUNT(*) FROM sections WHERE grade_id = g.grade_id) as section_count, (SELECT COUNT(*) FROM students st JOIN sections s ON st.section_id = s.section_id WHERE s.grade_id = g.grade_id AND st.status='active') as student_count FROM grades g ORDER BY g.grade_order")->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="row g-4">
        <!-- Form -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-plus-circle me-2"></i><?= $editGrade ? 'Edit Grade' : 'Add Grade' ?></div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('admin/grades') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="<?= $editGrade ? 'edit' : 'create' ?>">
                        <input type="hidden" name="grade_id" value="<?= $editGrade['grade_id'] ?? 0 ?>">
                        <div class="mb-3">
                            <label class="form-label">Grade Name *</label>
                            <input type="text" class="form-control" name="grade_name" value="<?= e($editGrade['grade_name'] ?? '') ?>" required placeholder="e.g., KG1, KG2, 1, 2, ...">
                            <small class="text-muted">Use KG1, KG2, KG3 for kindergarten, or numbers 1-8 for regular grades</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order *</label>
                            <input type="number" class="form-control" name="grade_order" value="<?= $editGrade['grade_order'] ?? (count($grades)) ?>" required min="0">
                            <small class="text-muted">Lower number = appears first (KG1=0, KG2=1, KG3=2, Grade 1=3, ...)</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                        <?php if ($editGrade): ?>
                        <a href="<?= buildUrl('admin/grades') ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- List -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-list-ol me-2"></i>All Grades</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Order</th><th>Grade Name</th><th>Display</th><th>Sections</th><th>Students</th><th><?= __('actions') ?></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $g): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= $g['grade_order'] ?></span></td>
                                    <td class="fw-semibold"><?= e($g['grade_name']) ?></td>
                                    <td><?= getGradeDisplayName($g['grade_name']) ?></td>
                                    <td><span class="badge bg-primary"><?= $g['section_count'] ?></span></td>
                                    <td><span class="badge bg-success"><?= $g['student_count'] ?></span></td>
                                    <td>
                                        <a href="<?= buildUrl('admin/grades', ['edit'=>$g['grade_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                                        <?php if ($g['section_count'] == 0): ?>
                                        <a href="<?= buildUrl('admin/grades', ['delete'=>$g['grade_id']]) ?>" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="Delete this grade?"><i class="bi bi-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
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
