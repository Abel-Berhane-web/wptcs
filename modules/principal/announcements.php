<?php
/**
 * Principal - Announcements Management
 */
requireRole('principal');

$pdo = getDBConnection();
$principalId = getCurrentUserId();
$pageTitle = __('announcements');
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) { setFlashMessage('danger', __('csrf_error')); header('Location: ' . buildUrl('principal/announcements')); exit; }
    
    $annId = intval($_POST['announcement_id'] ?? 0);
    $title = sanitize($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? 'general';
    $targetAudience = $_POST['target_audience'] ?? 'all';
    $targetGradeId = in_array($targetAudience, ['specific_grade', 'specific_parent']) ? intval($_POST['target_grade_id'] ?? 0) : null;
    $targetStudentId = ($targetAudience === 'specific_parent') ? intval($_POST['target_student_id'] ?? 0) : null;
    $publishDate = $_POST['publish_date'] ?? date('Y-m-d');
    $expiryDate = $_POST['expiry_date'] ?: null;
    
    // Handle attachment
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $result = uploadFile($_FILES['attachment'], UPLOADS_PATH);
        if ($result['success']) {
            $attachment = $result['filename'];
        } else {
            setFlashMessage('danger', $result['message']);
            header('Location: ' . buildUrl('principal/announcements', ['action'=>'create']));
            exit;
        }
    }
    
    try {
        if ($annId > 0) {
            $sql = "UPDATE announcements SET title=:title, content=:content, type=:type, target_audience=:ta, target_grade_id=:tgid, target_student_id=:tsid, publish_date=:pd, expiry_date=:ed";
            $params = [':title'=>$title, ':content'=>$content, ':type'=>$type, ':ta'=>$targetAudience, ':tgid'=>$targetGradeId, ':tsid'=>$targetStudentId, ':pd'=>$publishDate, ':ed'=>$expiryDate, ':id'=>$annId];
            if ($attachment) { $sql .= ", attachment=:att"; $params[':att'] = $attachment; }
            $sql .= " WHERE announcement_id=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            setFlashMessage('success', __('announcement_updated'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, type, target_audience, target_grade_id, target_student_id, attachment, posted_by, publish_date, expiry_date) VALUES (:title, :content, :type, :ta, :tgid, :tsid, :att, :pby, :pd, :ed)");
            $stmt->execute([':title'=>$title, ':content'=>$content, ':type'=>$type, ':ta'=>$targetAudience, ':tgid'=>$targetGradeId, ':tsid'=>$targetStudentId, ':att'=>$attachment, ':pby'=>$principalId, ':pd'=>$publishDate, ':ed'=>$expiryDate]);
            
            // Create notifications for target audience
            $targetUsers = [];
            if ($targetAudience === 'all' || $targetAudience === 'teachers') {
                $users = $pdo->query("SELECT user_id FROM users WHERE role = 'teacher' AND is_active = 1")->fetchAll();
                foreach ($users as $u) $targetUsers[] = $u['user_id'];
            }
            if ($targetAudience === 'all' || $targetAudience === 'parents') {
                $users = $pdo->query("SELECT user_id FROM users WHERE role = 'parent' AND is_active = 1")->fetchAll();
                foreach ($users as $u) $targetUsers[] = $u['user_id'];
            }
            if ($targetAudience === 'specific_parent' && $targetStudentId) {
                $stmt = $pdo->prepare("SELECT parent_id FROM students WHERE student_id = :sid");
                $stmt->execute([':sid' => $targetStudentId]);
                $pid = $stmt->fetchColumn();
                if ($pid) $targetUsers[] = $pid;
            }
            foreach ($targetUsers as $uid) {
                createNotification($uid, __('announcement'), $title, 'info', buildUrl('shared/announcements-view'));
            }
            
            setFlashMessage('success', __('announcement_created'));
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
    }
    header('Location: ' . buildUrl('principal/announcements'));
    exit;
}

// Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE announcements SET is_active = 0 WHERE announcement_id = :id")->execute([':id' => intval($_GET['id'])]);
    setFlashMessage('success', __('announcement_deleted'));
    header('Location: ' . buildUrl('principal/announcements'));
    exit;
}

$editAnn = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE announcement_id = :id");
    $stmt->execute([':id' => intval($_GET['id'])]);
    $editAnn = $stmt->fetch();
}

$announcements = $pdo->query("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as poster_name, CONCAT(st.first_name,' ',st.last_name) as target_student_name FROM announcements a JOIN users u ON a.posted_by = u.user_id LEFT JOIN students st ON a.target_student_id = st.student_id WHERE a.is_active = 1 ORDER BY a.created_at DESC")->fetchAll();
$grades = $pdo->query("SELECT * FROM grades WHERE status = 'active' ORDER BY grade_order")->fetchAll();
$sections = $pdo->query("SELECT section_id, grade_id, section_name FROM sections WHERE status = 'active' ORDER BY section_name")->fetchAll();
$allStudents = $pdo->query("SELECT student_id, section_id, parent_id, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-megaphone me-2"></i><?= $editAnn ? __('edit') : __('post_announcement') ?></span>
            <a href="<?= buildUrl('principal/announcements') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= __('back') ?></a>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= buildUrl('principal/announcements') ?>" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="announcement_id" value="<?= $editAnn['announcement_id'] ?? 0 ?>">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label"><?= __('title') ?> *</label>
                        <input type="text" class="form-control" name="title" value="<?= e($editAnn['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('type') ?></label>
                        <select class="form-select" name="type">
                            <option value="general" <?= ($editAnn['type'] ?? '') === 'general' ? 'selected' : '' ?>><?= __('general') ?></option>
                            <option value="exam_schedule" <?= ($editAnn['type'] ?? '') === 'exam_schedule' ? 'selected' : '' ?>><?= __('exam_schedule') ?></option>
                            <option value="meeting" <?= ($editAnn['type'] ?? '') === 'meeting' ? 'selected' : '' ?>><?= __('meeting') ?></option>
                            <option value="event" <?= ($editAnn['type'] ?? '') === 'event' ? 'selected' : '' ?>><?= __('event') ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= __('content') ?> *</label>
                        <textarea class="form-control" name="content" rows="5" required><?= e($editAnn['content'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('target_audience') ?></label>
                        <select class="form-select" name="target_audience" id="targetAudience">
                            <option value="all" <?= ($editAnn['target_audience'] ?? '') === 'all' ? 'selected' : '' ?>><?= __('all') ?></option>
                            <option value="teachers" <?= ($editAnn['target_audience'] ?? '') === 'teachers' ? 'selected' : '' ?>><?= __('teachers') ?></option>
                            <option value="parents" <?= ($editAnn['target_audience'] ?? '') === 'parents' ? 'selected' : '' ?>><?= __('parents') ?></option>
                            <option value="specific_grade" <?= ($editAnn['target_audience'] ?? '') === 'specific_grade' ? 'selected' : '' ?>><?= __('specific_grade') ?></option>
                            <option value="specific_parent" <?= ($editAnn['target_audience'] ?? '') === 'specific_parent' ? 'selected' : '' ?>>Specific Parent / Student</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="gradeSelectDiv" style="display:<?= in_array($editAnn['target_audience'] ?? '', ['specific_grade', 'specific_parent']) ? 'block' : 'none' ?>">
                        <label class="form-label"><?= __('grade') ?></label>
                        <select class="form-select" name="target_grade_id" id="gradeFilter">
                            <option value="">Select Grade...</option>
                            <?php foreach ($grades as $g): ?>
                            <option value="<?= $g['grade_id'] ?>" <?= ($editAnn['target_grade_id'] ?? '') == $g['grade_id'] ? 'selected' : '' ?>><?= e($g['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $g['grade_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4" id="sectionSelectDiv" style="display:<?= ($editAnn['target_audience'] ?? '') === 'specific_parent' ? 'block' : 'none' ?>">
                        <label class="form-label">Section</label>
                        <select class="form-select" id="sectionFilter">
                            <option value="">Select Section...</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="studentSelectDiv" style="display:<?= ($editAnn['target_audience'] ?? '') === 'specific_parent' ? 'block' : 'none' ?>">
                        <label class="form-label">Student</label>
                        <select class="form-select" name="target_student_id" id="studentFilter">
                            <option value="">Select Student...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('publish_date') ?></label>
                        <input type="date" class="form-control" name="publish_date" value="<?= e($editAnn['publish_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('expiry_date') ?></label>
                        <input type="date" class="form-control" name="expiry_date" value="<?= e($editAnn['expiry_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= __('attachment') ?></label>
                        <input type="file" class="form-control" name="attachment">
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
    <script>
    const sectionsData = <?= json_encode($sections) ?>;
    const studentsData = <?= json_encode($allStudents) ?>;

    const targetAudience = document.getElementById('targetAudience');
    const gradeDiv = document.getElementById('gradeSelectDiv');
    const sectionDiv = document.getElementById('sectionSelectDiv');
    const studentDiv = document.getElementById('studentSelectDiv');

    const gradeFilter = document.getElementById('gradeFilter');
    const sectionFilter = document.getElementById('sectionFilter');
    const studentFilter = document.getElementById('studentFilter');

    // Display fields based on target audience
    targetAudience.addEventListener('change', function() {
        gradeDiv.style.display = ['specific_grade', 'specific_parent'].includes(this.value) ? 'block' : 'none';
        sectionDiv.style.display = this.value === 'specific_parent' ? 'block' : 'none';
        studentDiv.style.display = this.value === 'specific_parent' ? 'block' : 'none';
    });

    // Populate sections when grade is selected
    gradeFilter.addEventListener('change', function() {
        const gid = parseInt(this.value);
        sectionFilter.innerHTML = '<option value="">Select Section...</option>';
        studentFilter.innerHTML = '<option value="">Select Student...</option>';
        if(!gid) return;

        sectionsData.filter(s => s.grade_id == gid).forEach(s => {
            sectionFilter.innerHTML += `<option value="${s.section_id}">${s.section_name}</option>`;
        });
    });

    // Populate students when section is selected
    sectionFilter.addEventListener('change', function() {
        const sid = parseInt(this.value);
        studentFilter.innerHTML = '<option value="">Select Student...</option>';
        if(!sid) return;

        studentsData.filter(st => st.section_id == sid).forEach(st => {
            studentFilter.innerHTML += `<option value="${st.student_id}">${st.first_name} ${st.last_name}</option>`;
        });
    });
    </script>
    
    <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><?= __('announcements') ?></h5>
        <a href="<?= buildUrl('principal/announcements', ['action'=>'create']) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i><?= __('post_announcement') ?></a>
    </div>
    
    <?php foreach ($announcements as $ann): ?>
    <div class="card announcement-card type-<?= e($ann['type']) ?> mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1"><?= e($ann['title']) ?></h6>
                    <div class="mb-2">
                        <span class="badge bg-<?= match($ann['type']){ 'exam_schedule'=>'warning','meeting'=>'info','event'=>'success',default=>'primary' } ?>"><?= __(e($ann['type'])) ?></span>
                        <span class="badge bg-light text-dark ms-1">
                            <?= $ann['target_audience'] === 'specific_parent' ? '<i class="bi bi-person me-1"></i> Parent of: ' . e($ann['target_student_name']) : __(e($ann['target_audience'])) ?>
                        </span>
                    </div>
                </div>
                <div>
                    <a href="<?= buildUrl('principal/announcements', ['action'=>'edit','id'=>$ann['announcement_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                    <a href="<?= buildUrl('principal/announcements', ['action'=>'delete','id'=>$ann['announcement_id']]) ?>" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="<?= __('delete_confirm') ?>"><i class="bi bi-trash"></i></a>
                </div>
            </div>
            <p class="text-muted small mb-2"><?= nl2br(e($ann['content'])) ?></p>
            <small class="text-muted">
                <i class="bi bi-person me-1"></i><?= e($ann['poster_name']) ?>
                <span class="ms-3"><i class="bi bi-calendar me-1"></i><?= formatDate($ann['created_at']) ?></span>
                <?php if ($ann['attachment']): ?>
                <span class="ms-3"><i class="bi bi-paperclip me-1"></i><a href="<?= BASE_URL ?>/assets/uploads/<?= e($ann['attachment']) ?>"><?= __('attachment') ?></a></span>
                <?php endif; ?>
            </small>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($announcements)): ?>
    <div class="empty-state">
        <i class="bi bi-megaphone"></i>
        <h5><?= __('no_data') ?></h5>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
