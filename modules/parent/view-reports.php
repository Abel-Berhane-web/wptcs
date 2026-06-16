<?php
/**
 * Parent - View Weekly Reports
 */
requireRole('parent');

$pdo = getDBConnection();
$parentId = getCurrentUserId();
$pageTitle = __('weekly_reports');

$studentId = intval($_GET['student_id'] ?? 0);

$stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM students WHERE parent_id = :pid AND status = 'active'");
$stmt->execute([':pid' => $parentId]);
$children = $stmt->fetchAll();

$reports = [];
$student = null;
$categories = $pdo->query("SELECT * FROM weekly_report_categories WHERE status = 'active' ORDER BY sort_order")->fetchAll();

if ($studentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :sid AND parent_id = :pid");
    $stmt->execute([':sid' => $studentId, ':pid' => $parentId]);
    $student = $stmt->fetch();
    
    if ($student) {
        $stmt = $pdo->prepare("
            SELECT wr.*, CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM weekly_reports wr
            JOIN users t ON wr.teacher_id = t.user_id
            WHERE wr.student_id = :sid
            ORDER BY wr.report_year DESC, wr.week_number DESC
        ");
        $stmt->execute([':sid' => $studentId]);
        $reports = $stmt->fetchAll();
    }
}

$lang = getCurrentLanguage();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-file-earmark-bar-graph me-2"></i><?= __('weekly_reports') ?></h5>
    
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
            <input type="hidden" name="page" value="parent/view-reports">
            <div class="form-group">
                <label><?= __('student') ?></label>
                <select class="form-select form-select-sm" name="student_id" onchange="this.form.submit()">
                    <option value=""><?= __('select') ?>...</option>
                    <?php foreach ($children as $c): ?>
                    <option value="<?= $c['student_id'] ?>" <?= $studentId == $c['student_id'] ? 'selected' : '' ?>><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    
    <?php if ($student && !empty($reports)): ?>
    <?php foreach ($reports as $r): 
        $metrics = json_decode($r['metrics'], true) ?: [];
        $characterTheme = $r['character_theme'] ?? '';
        $avgRating = count($metrics) > 0 ? round(array_sum($metrics) / count($metrics), 1) : 0;
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><strong><?= __('week') ?> <?= $r['week_number'] ?></strong> — <?= $r['report_year'] ?></span>
            <div>
                <?= starRating(round($avgRating)) ?>
                <span class="ms-2 badge bg-primary"><?= $avgRating ?>/5</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($categories as $cat): 
                    $catRating = $metrics[$cat['category_id']] ?? 0;
                    $catName = __($cat['category_name']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                        <small><?= e($catName) ?></small>
                        <span class="star-rating" style="font-size:14px;"><?= starRating($catRating) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($characterTheme)): ?>
            <div class="mt-4 p-3 bg-light border-start border-4 border-primary rounded">
                <strong class="small d-block mb-1 text-primary">የሳምንቱ የሥነ-ምግባር መሪ ቃል / Character Theme discussed in the week:</strong>
                <p class="mb-0 fw-bold"><?= e($characterTheme) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($r['overall_comment']): ?>
            <div class="mt-3 p-3 bg-light rounded">
                <strong class="small"><?= __('overall_comment') ?>:</strong>
                <p class="mb-0 small"><?= e($r['overall_comment']) ?></p>
            </div>
            <?php endif; ?>
            
            <small class="text-muted d-block mt-2">
                <i class="bi bi-person me-1"></i><?= __('homeroom_teacher') ?>: <?= e($r['teacher_name']) ?>
                <span class="ms-3"><i class="bi bi-calendar me-1"></i><?= formatDate($r['created_at']) ?></span>
            </small>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php elseif ($student): ?>
    <div class="empty-state">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <h5><?= __('no_data') ?></h5>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
