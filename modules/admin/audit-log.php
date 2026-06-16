<?php
/**
 * Admin - Audit Log
 */
requireRole('admin');

$pdo = getDBConnection();
$pageTitle = __('audit_log');
$page_num = max(1, intval($_GET['p'] ?? 1));

$countStmt = $pdo->query("SELECT COUNT(*) FROM audit_log");
$totalRecords = $countStmt->fetchColumn();
$pagination = paginate($totalRecords, $page_num);

$stmt = $pdo->prepare("SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role 
    FROM audit_log al LEFT JOIN users u ON al.user_id = u.user_id 
    ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><?= __('audit_log') ?> <span class="badge bg-primary ms-2"><?= $totalRecords ?></span></h5>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th><?= __('name') ?></th><th><?= __('role') ?></th><th>Action</th><th>Table</th><th>IP</th><th><?= __('date') ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $i => $log): ?>
                        <tr>
                            <td><?= $pagination['offset'] + $i + 1 ?></td>
                            <td class="fw-semibold"><?= e($log['user_name'] ?? 'System') ?></td>
                            <td><span class="badge bg-secondary"><?= e($log['role'] ?? '-') ?></span></td>
                            <td><code><?= e($log['action']) ?></code></td>
                            <td><?= e($log['table_name'] ?? '-') ?></td>
                            <td><small class="text-muted"><?= e($log['ip_address'] ?? '-') ?></small></td>
                            <td><small><?= formatDateTime($log['created_at']) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?= renderPagination($pagination, buildUrl('admin/audit-log')) ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
