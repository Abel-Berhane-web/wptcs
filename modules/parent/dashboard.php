<?php
/**
 * Parent Dashboard
 */
requireRole('parent');

$pdo = getDBConnection();
$parentId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('dashboard');

// Get children
$stmt = $pdo->prepare("
    SELECT st.*, sec.section_name, g.grade_name, g.grade_order,
           sec.homeroom_teacher_id, CONCAT(ht.first_name, ' ', ht.last_name) as homeroom_teacher_name
    FROM students st
    LEFT JOIN sections sec ON st.section_id = sec.section_id
    LEFT JOIN grades g ON sec.grade_id = g.grade_id
    LEFT JOIN users ht ON sec.homeroom_teacher_id = ht.user_id
    WHERE st.parent_id = :pid AND st.status = 'active'
    ORDER BY g.grade_order
");
$stmt->execute([':pid' => $parentId]);
$children = $stmt->fetchAll();

// Get attendance summary for each child
foreach ($children as &$child) {
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as cnt
        FROM attendance WHERE student_id = :sid
        AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY status
    ");
    $stmt->execute([':sid' => $child['student_id']]);
    $child['attendance_summary'] = [];
    foreach ($stmt->fetchAll() as $a) {
        $child['attendance_summary'][$a['status']] = $a['cnt'];
    }

    // Overall marks average
    $child['avg_score'] = calculateStudentAverage($child['student_id'], $currentYear);
}
unset($child);

// Unread comments
$unreadComments = getUnreadCommentsCount($parentId);

// Recent announcements
$stmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 AND (target_audience = 'all' OR target_audience = 'parents') ORDER BY created_at DESC LIMIT 3");
$recentAnnouncements = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <!-- Welcome -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #7c3aed, #a855f7); border: none;">
        <div class="card-body py-4">
            <h4 class="text-white mb-2"><?= __('dear_parent') ?> <?= e($_SESSION['full_name']) ?>, <?= __('hello_how_are_you') ?> <span class="wave-icon">👋</span></h4>
            <p class="text-white opacity-90 mb-0" style="font-size: 1.05rem; line-height: 1.6;">
                <?= __('dashboard_parent_msg') ?>
            </p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-number"><?= count($children) ?></div>
                <div class="stat-label"><?= __('my_children') ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-info">
                <div class="stat-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <div class="stat-number"><?= $unreadComments ?></div>
                <div class="stat-label"><?= __('unread') ?> <?= __('comments') ?></div>
            </div>
        </div>
    </div>

    <!-- Children Cards -->
    <div class="section-header">
        <i class="bi bi-people"></i>
        <h5><?= __('my_children') ?></h5>
    </div>

    <?php if (empty($children)): ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            <h5><?= __('no_data') ?></h5>
            <p>No children are linked to your account. Contact the administrator.</p>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($children as $child): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="user-avatar" style="width:48px;height:48px;">
                                    <div class="avatar-initials"
                                        style="font-size:16px;background:linear-gradient(135deg,<?= $child['gender'] === 'male' ? '#2563eb,#3b82f6' : '#ec4899,#f472b6' ?>)">
                                        <?= strtoupper(substr($child['first_name'], 0, 1)) ?>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?= e($child['first_name'] . ' ' . $child['last_name']) ?></h6>
                                    <small class="text-muted">
                                        <?= $child['grade_name'] ? e(($child['grade_name'] === 'KG' ? 'KG' : 'Grade ' . $child['grade_name']) . '-' . $child['section_name']) : 'Unassigned' ?>
                                    </small>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="p-2 rounded bg-light text-center">
                                        <div class="fw-bold text-primary"><?= $child['avg_score'] ?>%</div>
                                        <small class="text-muted"><?= __('average') ?></small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded bg-light text-center">
                                        <?php $presentDays = $child['attendance_summary']['present'] ?? 0;
                                        $totalDays = array_sum($child['attendance_summary']) ?: 1;
                                        $attendanceRate = round(($presentDays / $totalDays) * 100); ?>
                                        <div class="fw-bold text-success"><?= $attendanceRate ?>%</div>
                                        <small class="text-muted"><?= __('attendance') ?></small>
                                    </div>
                                </div>
                            </div>

                            <?php if ($child['homeroom_teacher_name']): ?>
                                <small class="text-muted d-block mb-3"><i
                                        class="bi bi-person-workspace me-1"></i><?= __('homeroom_teacher') ?>:
                                    <?= e($child['homeroom_teacher_name']) ?></small>
                            <?php endif; ?>

                            <div class="d-flex gap-2 flex-wrap">
                                <a href="<?= buildUrl('parent/view-marks', ['student_id' => $child['student_id']]) ?>"
                                    class="btn btn-sm btn-outline-primary"><i
                                        class="bi bi-card-checklist me-1"></i><?= __('marks') ?></a>
                                <a href="<?= buildUrl('parent/view-reports', ['student_id' => $child['student_id']]) ?>"
                                    class="btn btn-sm btn-outline-info"><i
                                        class="bi bi-file-earmark-bar-graph me-1"></i><?= __('reports') ?></a>
                                <a href="<?= buildUrl('parent/view-attendance', ['student_id' => $child['student_id']]) ?>"
                                    class="btn btn-sm btn-outline-success"><i
                                        class="bi bi-clipboard-check me-1"></i><?= __('attendance') ?></a>
                                <a href="<?= buildUrl('shared/comments', ['student_id' => $child['student_id'], 'receiver_id' => $child['homeroom_teacher_id'] ?? 0]) ?>"
                                    class="btn btn-sm btn-outline-secondary"><i
                                        class="bi bi-chat-dots me-1"></i><?= __('comments') ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Recent Announcements -->
    <?php if (!empty($recentAnnouncements)): ?>
        <div class="section-header">
            <i class="bi bi-megaphone"></i>
            <h5><?= __('announcements') ?></h5>
        </div>
        <?php foreach ($recentAnnouncements as $ann): ?>
            <div class="card announcement-card type-<?= e($ann['type']) ?> mb-3">
                <div class="card-body">
                    <h6><?= e($ann['title']) ?></h6>
                    <p class="text-muted small mb-1">
                        <?= e(substr($ann['content'], 0, 200)) ?>         <?= strlen($ann['content']) > 200 ? '...' : '' ?>
                    </p>
                    <small class="text-muted"><i class="bi bi-calendar me-1"></i><?= formatDate($ann['created_at']) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>