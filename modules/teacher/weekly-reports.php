<?php
/**
 * Teacher - Weekly Reports (Homeroom teacher only)
 * 17 behavioral metrics rated 1-5
 */
requireRole('teacher');

$pdo = getDBConnection();
$teacherId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('weekly_reports');

// Load report categories
$categories = $pdo->query("SELECT * FROM weekly_report_categories WHERE status = 'active' ORDER BY sort_order")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
        header('Location: ' . buildUrl('teacher/weekly-reports'));
        exit;
    }
    
    $sectionId = intval($_POST['section_id'] ?? 0);
    $studentId = intval($_POST['student_id'] ?? 0);
    $weekNumber = intval($_POST['week_number'] ?? 0);
    $reportYear = intval($_POST['report_year'] ?? date('Y'));
    $overallComment = sanitize($_POST['overall_comment'] ?? '');
    $characterTheme = sanitize($_POST['character_theme'] ?? '');
    $ratings = $_POST['ratings'] ?? [];
    $reportId = intval($_POST['report_id'] ?? 0);
    
    // Validate homeroom teacher
    if (!isHomeroomTeacher($teacherId, $sectionId)) {
        setFlashMessage('danger', __('unauthorized'));
        header('Location: ' . buildUrl('teacher/weekly-reports'));
        exit;
    }
    
    // Build metrics JSON
    $metrics = [];
    foreach ($categories as $cat) {
        $rating = intval($ratings[$cat['category_id']] ?? 3);
        $rating = max(MIN_RATING, min(MAX_RATING, $rating));
        $metrics[$cat['category_id']] = $rating;
    }
    $metricsJson = json_encode($metrics);
    
    try {
        if ($reportId > 0) {
            $stmt = $pdo->prepare("UPDATE weekly_reports SET metrics=:metrics, overall_comment=:comment, character_theme=:ctheme WHERE report_id=:id AND teacher_id=:tid");
            $stmt->execute([':metrics'=>$metricsJson, ':comment'=>$overallComment, ':ctheme'=>$characterTheme, ':id'=>$reportId, ':tid'=>$teacherId]);
            setFlashMessage('success', __('report_updated'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO weekly_reports (student_id, teacher_id, section_id, week_number, report_year, metrics, overall_comment, character_theme) VALUES (:sid, :tid, :secid, :wk, :yr, :metrics, :comment, :ctheme)");
            $stmt->execute([':sid'=>$studentId, ':tid'=>$teacherId, ':secid'=>$sectionId, ':wk'=>$weekNumber, ':yr'=>$reportYear, ':metrics'=>$metricsJson, ':comment'=>$overallComment, ':ctheme'=>$characterTheme]);
            
            // Notify parent
            $stmt = $pdo->prepare("SELECT parent_id FROM students WHERE student_id = :sid");
            $stmt->execute([':sid' => $studentId]);
            $parentId = $stmt->fetchColumn();
            if ($parentId) {
                createNotification($parentId, __('weekly_report'), "A new weekly report has been created for your child.", 'info', buildUrl('parent/view-reports'));
            }
            
            setFlashMessage('success', __('report_created'));
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            setFlashMessage('warning', __('report_already_exists'));
        } else {
            setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
        }
    }
    
    header('Location: ' . buildUrl('teacher/weekly-reports', ['section_id' => $sectionId]));
    exit;
}

// Get homeroom sections
$stmt = $pdo->prepare("
    SELECT s.section_id, s.section_name, g.grade_name
    FROM sections s JOIN grades g ON s.grade_id = g.grade_id
    WHERE s.homeroom_teacher_id = :tid AND s.academic_year_id = :yid AND s.status = 'active'
    ORDER BY g.grade_order
");
$stmt->execute([':tid' => $teacherId, ':yid' => $currentYear]);
$homeroomSections = $stmt->fetchAll();

$selectedSection = intval($_GET['section_id'] ?? ($homeroomSections[0]['section_id'] ?? 0));
$action = $_GET['action'] ?? 'list';
$selectedStudent = intval($_GET['student_id'] ?? 0);

$students = [];
$reports = [];

if ($selectedSection && isHomeroomTeacher($teacherId, $selectedSection)) {
    $stmt = $pdo->prepare("SELECT student_id, student_code, first_name, last_name FROM students WHERE section_id = :sid AND status = 'active' ORDER BY first_name");
    $stmt->execute([':sid' => $selectedSection]);
    $students = $stmt->fetchAll();
    
    // Get recent reports
    $stmt = $pdo->prepare("
        SELECT wr.*, CONCAT(s.first_name, ' ', s.last_name) as student_name
        FROM weekly_reports wr
        JOIN students s ON wr.student_id = s.student_id
        WHERE wr.section_id = :sid AND wr.teacher_id = :tid
        ORDER BY wr.report_year DESC, wr.week_number DESC
        LIMIT 50
    ");
    $stmt->execute([':sid' => $selectedSection, ':tid' => $teacherId]);
    $reports = $stmt->fetchAll();
}

// Load report for editing
$editReport = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM weekly_reports WHERE report_id = :id AND teacher_id = :tid");
    $stmt->execute([':id' => intval($_GET['id']), ':tid' => $teacherId]);
    $editReport = $stmt->fetch();
}

$currentWeek = intval(date('W'));

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- ═══ Create/Edit Weekly Report ═══ -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-earmark-bar-graph me-2"></i><?= $editReport ? __('edit') . ' ' . __('weekly_report') : __('create_weekly_report') ?></span>
            <a href="<?= buildUrl('teacher/weekly-reports', ['section_id' => $selectedSection]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= __('back') ?></a>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= buildUrl('teacher/weekly-reports') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="section_id" value="<?= $selectedSection ?>">
                <input type="hidden" name="report_id" value="<?= $editReport['report_id'] ?? 0 ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label"><?= __('student') ?> *</label>
                        <select class="form-select" name="student_id" required <?= $editReport ? 'disabled' : '' ?>>
                            <option value=""><?= __('select') ?>...</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['student_id'] ?>" <?= ($editReport['student_id'] ?? $selectedStudent) == $s['student_id'] ? 'selected' : '' ?>><?= e($s['first_name'] . ' ' . $s['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($editReport): ?><input type="hidden" name="student_id" value="<?= $editReport['student_id'] ?>"><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('week_number') ?> *</label>
                        <input type="number" class="form-control" name="week_number" min="1" max="52" value="<?= $editReport['week_number'] ?? $currentWeek ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year *</label>
                        <input type="number" class="form-control" name="report_year" value="<?= $editReport['report_year'] ?? date('Y') ?>" required>
                    </div>
                </div>
                
                <!-- Character Theme -->
                <div class="mb-4">
                    <label class="form-label" style="font-weight: 600;">Character Theme discussed in the week (የሳምንቱ የሥነ-ምግባር መሪ ቃል)</label>
                    <input type="text" class="form-control border-primary bg-light" name="character_theme" value="<?= e($editReport['character_theme'] ?? '') ?>" placeholder="e.g. Respect, Honesty, Responsibility...">
                </div>
                
                <!-- 17 Category Ratings -->
                <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-star me-2"></i>Behavioral Ratings (1-5)</h6>
                <div class="row g-3 mb-4">
                    <?php 
                    $existingMetrics = $editReport ? json_decode($editReport['metrics'], true) : [];
                    foreach ($categories as $cat): 
                        $catRating = $existingMetrics[$cat['category_id']] ?? 3;
                        $lang = getCurrentLanguage();
                        $catName = __($cat['category_name']);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label small"><?= e($catName) ?></label>
                        <div class="star-rating-input" data-name="ratings[<?= $cat['category_id'] ?>]">
                            <input type="hidden" name="ratings[<?= $cat['category_id'] ?>]" value="<?= $catRating ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $catRating ? 'active' : '' ?>"><?= $i <= $catRating ? '★' : '☆' ?></span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Overall Comment -->
                <div class="mb-3">
                    <label class="form-label"><?= __('overall_comment') ?></label>
                    <textarea class="form-control" name="overall_comment" rows="3" placeholder="<?= __('overall_comment') ?>..."><?= e($editReport['overall_comment'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <!-- ═══ Reports List ═══ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i><?= __('weekly_reports') ?></h5>
        <?php if ($selectedSection): ?>
        <a href="<?= buildUrl('teacher/weekly-reports', ['section_id' => $selectedSection, 'action' => 'create']) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i><?= __('create_weekly_report') ?>
        </a>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($homeroomSections)): ?>
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
            <input type="hidden" name="page" value="teacher/weekly-reports">
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
        </form>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th><?= __('student') ?></th><th><?= __('week') ?></th><th>Year</th><th><?= __('average') ?> <?= __('rating') ?></th><th><?= __('date') ?></th><th><?= __('actions') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $r):
                            $metrics = json_decode($r['metrics'], true) ?: [];
                            $avgRating = count($metrics) > 0 ? round(array_sum($metrics) / count($metrics), 1) : 0;
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['student_name']) ?></td>
                            <td><?= __('week') ?> <?= $r['week_number'] ?></td>
                            <td><?= $r['report_year'] ?></td>
                            <td><?= starRating(round($avgRating)) ?> <small class="text-muted">(<?= $avgRating ?>)</small></td>
                            <td><small><?= formatDate($r['created_at']) ?></small></td>
                            <td>
                                <a href="<?= buildUrl('teacher/weekly-reports', ['section_id'=>$selectedSection, 'action'=>'edit', 'id'=>$r['report_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reports)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted"><?= __('no_data') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-clipboard-x"></i>
        <h5>No Homeroom Classes</h5>
        <p>Only homeroom teachers can create weekly reports.</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
