<?php
/**
 * Shared - Announcements View (Read-only)
 */
requireLogin();

$pdo = getDBConnection();
$role = getCurrentUserRole();
$pageTitle = __('announcements');

// Build query based on role
$where = "WHERE a.is_active = 1";
if ($role === 'teacher') {
    $where .= " AND (a.target_audience IN ('all', 'teachers'))";
} elseif ($role === 'parent') {
    $pid = intval(getCurrentUserId());
    $where .= " AND (a.target_audience IN ('all', 'parents') 
                 OR (a.target_audience = 'specific_grade' AND a.target_grade_id IN (SELECT sec.grade_id FROM students st JOIN sections sec ON st.section_id = sec.section_id WHERE st.parent_id = $pid AND st.status = 'active'))
                 OR (a.target_audience = 'specific_parent' AND a.target_student_id IN (SELECT student_id FROM students WHERE parent_id = $pid AND status = 'active')))";
}

$stmt = $pdo->query("SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as poster_name FROM announcements a JOIN users u ON a.posted_by = u.user_id $where ORDER BY a.created_at DESC");
$announcements = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-megaphone me-2"></i><?= __('announcements') ?></h5>
    
    <?php if (empty($announcements)): ?>
    <div class="empty-state">
        <i class="bi bi-megaphone"></i>
        <h5><?= __('no_data') ?></h5>
    </div>
    <?php endif; ?>
    
    <?php foreach ($announcements as $ann): ?>
    <div class="card announcement-card type-<?= e($ann['type']) ?> mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="mb-0"><?= e($ann['title']) ?></h6>
                <span class="badge bg-<?= match($ann['type']){ 'exam_schedule'=>'warning','meeting'=>'info','event'=>'success',default=>'primary' } ?>"><?= __(e($ann['type'])) ?></span>
            </div>
            <p class="mb-2"><?= nl2br(e($ann['content'])) ?></p>
            <?php if ($ann['attachment']): ?>
            <div class="mb-2">
                <a href="<?= BASE_URL ?>/assets/uploads/<?= e($ann['attachment']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="bi bi-paperclip me-1"></i><?= __('attachment') ?>
                </a>
            </div>
            <?php endif; ?>
            <small class="text-muted">
                <i class="bi bi-person me-1"></i><?= e($ann['poster_name']) ?>
                <span class="ms-3"><i class="bi bi-calendar me-1"></i><?= formatDate($ann['created_at']) ?></span>
                <span class="ms-3 badge bg-light text-dark"><?= __(e($ann['target_audience'])) ?></span>
            </small>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
