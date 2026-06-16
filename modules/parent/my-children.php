<?php
/**
 * Parent - My Children
 */
requireRole('parent');

$pdo = getDBConnection();
$parentId = getCurrentUserId();
$pageTitle = __('my_children');

$stmt = $pdo->prepare("
    SELECT st.*, sec.section_name, g.grade_name,
           CONCAT(ht.first_name, ' ', ht.last_name) as homeroom_teacher_name, ht.phone as teacher_phone
    FROM students st
    LEFT JOIN sections sec ON st.section_id = sec.section_id
    LEFT JOIN grades g ON sec.grade_id = g.grade_id
    LEFT JOIN users ht ON sec.homeroom_teacher_id = ht.user_id
    WHERE st.parent_id = :pid
    ORDER BY g.grade_order
");
$stmt->execute([':pid' => $parentId]);
$children = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-people me-2"></i><?= __('my_children') ?></h5>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th><?= __('student_code') ?></th><th><?= __('name') ?></th><th><?= __('grade') ?>/<?= __('section') ?></th><th><?= __('gender') ?></th><th><?= __('homeroom_teacher') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $c): ?>
                        <tr>
                            <td><code><?= e($c['student_code']) ?></code></td>
                            <td class="fw-semibold"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td>
                            <td><?= $c['grade_name'] ? e(($c['grade_name']==='KG' ? 'KG' : 'Grade '.$c['grade_name']).'-'.$c['section_name']) : '-' ?></td>
                            <td><i class="bi bi-<?= $c['gender']==='male'?'gender-male text-primary':'gender-female text-danger' ?>"></i></td>
                            <td><?= e($c['homeroom_teacher_name'] ?? '-') ?></td>
                            <td><span class="badge bg-<?= $c['status']==='active'?'success':'secondary' ?>"><?= __(e($c['status'])) ?></span></td>
                            <td>
                                <a href="<?= buildUrl('parent/view-marks', ['student_id'=>$c['student_id']]) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="<?= __('marks') ?>"><i class="bi bi-card-checklist"></i></a>
                                <a href="<?= buildUrl('parent/view-reports', ['student_id'=>$c['student_id']]) ?>" class="btn btn-sm btn-outline-info btn-icon" title="<?= __('reports') ?>"><i class="bi bi-file-earmark-bar-graph"></i></a>
                                <a href="<?= buildUrl('parent/view-attendance', ['student_id'=>$c['student_id']]) ?>" class="btn btn-sm btn-outline-success btn-icon" title="<?= __('attendance') ?>"><i class="bi bi-clipboard-check"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($children)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted"><?= __('no_data') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
