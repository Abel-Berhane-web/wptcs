<?php
/**
 * Admin - Subjects Management
 */
requireRole('admin');

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$pageTitle = __('manage_subjects');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) { setFlashMessage('danger', __('csrf_error')); header('Location: ' . buildUrl('admin/subjects')); exit; }
    
    $subjectId = intval($_POST['subject_id'] ?? 0);
    $subjectName = sanitize($_POST['subject_name'] ?? '');
    $subjectNameAm = ''; // Removed from DB
    $subjectCode = strtoupper(sanitize($_POST['subject_code'] ?? ''));
    $status = $_POST['status'] ?? 'active';
    
    try {
        if ($subjectId > 0) {
            $stmt = $pdo->prepare("UPDATE subjects SET subject_name=:name, subject_code=:code, status=:st WHERE subject_id=:id");
            $stmt->execute([':name'=>$subjectName, ':code'=>$subjectCode, ':st'=>$status, ':id'=>$subjectId]);
            setFlashMessage('success', __('subject_updated'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, status) VALUES (:name, :code, :st)");
            $stmt->execute([':name'=>$subjectName, ':code'=>$subjectCode, ':st'=>$status]);
            setFlashMessage('success', __('subject_created'));
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
    }
    header('Location: ' . buildUrl('admin/subjects'));
    exit;
}

$editSubject = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE subject_id = :id");
    $stmt->execute([':id' => intval($_GET['id'])]);
    $editSubject = $stmt->fetch();
}

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-book me-2"></i><?= $editSubject ? __('edit') : __('create_subject') ?></span>
            <a href="<?= buildUrl('admin/subjects') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= __('back') ?></a>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= buildUrl('admin/subjects') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="subject_id" value="<?= $editSubject['subject_id'] ?? 0 ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?= __('subject_name') ?> * (English)</label>
                        <input type="text" class="form-control" name="subject_name" value="<?= e($editSubject['subject_name'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label"><?= __('subject_code') ?> *</label>
                        <input type="text" class="form-control" name="subject_code" value="<?= e($editSubject['subject_code'] ?? '') ?>" required maxlength="10">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><?= __('status') ?></label>
                        <select class="form-select" name="status">
                            <option value="active" <?= ($editSubject['status'] ?? 'active') === 'active' ? 'selected' : '' ?>><?= __('active') ?></option>
                            <option value="inactive" <?= ($editSubject['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= __('inactive') ?></option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                    <a href="<?= buildUrl('admin/subjects') ?>" class="btn btn-outline-secondary ms-2"><?= __('cancel') ?></a>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><?= __('subjects') ?></h5>
        <a href="<?= buildUrl('admin/subjects', ['action'=>'create']) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i><?= __('create_subject') ?></a>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>#</th><th><?= __('subject_code') ?></th><th><?= __('subject_name') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $i => $sub): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><code><?= e($sub['subject_code']) ?></code></td>
                            <td class="fw-semibold"><?= e(translateSubject($sub['subject_name'])) ?></td>

                            <td><span class="badge bg-<?= $sub['status'] === 'active' ? 'success' : 'secondary' ?>"><?= __(e($sub['status'])) ?></span></td>
                            <td>
                                <a href="<?= buildUrl('admin/subjects', ['action'=>'edit', 'id'=>$sub['subject_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
