<?php
/**
 * Admin - Academic Years Management
 */
requireRole('admin');

$pdo = getDBConnection();
$pageTitle = __('academic_years');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) { setFlashMessage('danger', __('csrf_error')); header('Location: ' . buildUrl('admin/academic-years')); exit; }
    
    $yearId = intval($_POST['year_id'] ?? 0);
    $yearName = sanitize($_POST['year_name'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $isCurrent = isset($_POST['is_current']) ? 1 : 0;
    
    try {
        if ($isCurrent) {
            $pdo->exec("UPDATE academic_years SET is_current = 0");
        }
        
        if ($yearId > 0) {
            $stmt = $pdo->prepare("UPDATE academic_years SET year_name=:name, start_date=:sd, end_date=:ed, is_current=:ic WHERE year_id=:id");
            $stmt->execute([':name'=>$yearName, ':sd'=>$startDate, ':ed'=>$endDate, ':ic'=>$isCurrent, ':id'=>$yearId]);
            setFlashMessage('success', __('year_updated'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO academic_years (year_name, start_date, end_date, is_current) VALUES (:name, :sd, :ed, :ic)");
            $stmt->execute([':name'=>$yearName, ':sd'=>$startDate, ':ed'=>$endDate, ':ic'=>$isCurrent]);
            setFlashMessage('success', __('year_created'));
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
    }
    header('Location: ' . buildUrl('admin/academic-years'));
    exit;
}

$years = $pdo->query("SELECT * FROM academic_years ORDER BY year_id DESC")->fetchAll();
$editYear = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE year_id = :id");
    $stmt->execute([':id' => intval($_GET['edit'])]);
    $editYear = $stmt->fetch();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-calendar3 me-2"></i><?= $editYear ? __('edit') : __('create_academic_year') ?></div>
                <div class="card-body">
                    <form method="POST" action="<?= buildUrl('admin/academic-years') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="year_id" value="<?= $editYear['year_id'] ?? 0 ?>">
                        <div class="mb-3">
                            <label class="form-label"><?= __('year_name') ?> * (e.g. 2025-2026)</label>
                            <input type="text" class="form-control" name="year_name" value="<?= e($editYear['year_name'] ?? '') ?>" required placeholder="2025-2026">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('start_date') ?> *</label>
                            <input type="date" class="form-control" name="start_date" value="<?= e($editYear['start_date'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('end_date') ?> *</label>
                            <input type="date" class="form-control" name="end_date" value="<?= e($editYear['end_date'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_current" id="isCurrent" <?= ($editYear['is_current'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isCurrent"><?= __('current_year') ?></label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><?= __('academic_years') ?></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th><?= __('year_name') ?></th><th><?= __('start_date') ?></th><th><?= __('end_date') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($years as $y): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($y['year_name']) ?></td>
                                    <td><?= formatDate($y['start_date']) ?></td>
                                    <td><?= formatDate($y['end_date']) ?></td>
                                    <td>
                                        <?php if ($y['is_current']): ?>
                                        <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i><?= __('current_year') ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary"><?= __(e($y['status'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="<?= buildUrl('admin/academic-years', ['edit'=>$y['year_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a></td>
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
