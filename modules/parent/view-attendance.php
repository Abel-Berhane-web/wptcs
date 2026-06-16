<?php
/**
 * Parent - View Attendance
 */
requireRole('parent');

$pdo = getDBConnection();
$parentId = getCurrentUserId();
$pageTitle = __('view_attendance');

$studentId = intval($_GET['student_id'] ?? 0);
$selectedMonth = $_GET['month'] ?? date('Y-m');

$stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM students WHERE parent_id = :pid AND status = 'active'");
$stmt->execute([':pid' => $parentId]);
$children = $stmt->fetchAll();

$student = null;
$attendanceData = [];
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

if ($studentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :sid AND parent_id = :pid");
    $stmt->execute([':sid' => $studentId, ':pid' => $parentId]);
    $student = $stmt->fetch();
    
    if ($student) {
        $startDate = $selectedMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = :sid AND attendance_date BETWEEN :start AND :end ORDER BY attendance_date");
        $stmt->execute([':sid' => $studentId, ':start' => $startDate, ':end' => $endDate]);
        $records = $stmt->fetchAll();
        
        foreach ($records as $rec) {
            $attendanceData[$rec['attendance_date']] = $rec;
            $summary[$rec['status']]++;
        }
    }
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-clipboard-check me-2"></i><?= __('view_attendance') ?></h5>
    
    <div class="filter-bar">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="parent/view-attendance">
            <div class="form-group">
                <label><?= __('student') ?></label>
                <select class="form-select form-select-sm" name="student_id" onchange="this.form.submit()">
                    <option value=""><?= __('select') ?>...</option>
                    <?php foreach ($children as $c): ?>
                    <option value="<?= $c['student_id'] ?>" <?= $studentId == $c['student_id'] ? 'selected' : '' ?>><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Month</label>
                <input type="month" class="form-control form-control-sm" name="month" value="<?= e($selectedMonth) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
    
    <?php if ($student): ?>
    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-3">
            <div class="card text-center p-3" style="border-left:4px solid var(--success)">
                <div class="fw-bold text-success fs-4"><?= $summary['present'] ?></div>
                <small><?= __('present') ?></small>
            </div>
        </div>
        <div class="col-3">
            <div class="card text-center p-3" style="border-left:4px solid var(--danger)">
                <div class="fw-bold text-danger fs-4"><?= $summary['absent'] ?></div>
                <small><?= __('absent') ?></small>
            </div>
        </div>
        <div class="col-3">
            <div class="card text-center p-3" style="border-left:4px solid var(--warning)">
                <div class="fw-bold text-warning fs-4"><?= $summary['late'] ?></div>
                <small><?= __('late') ?></small>
            </div>
        </div>
        <div class="col-3">
            <div class="card text-center p-3" style="border-left:4px solid var(--info)">
                <div class="fw-bold text-info fs-4"><?= $summary['excused'] ?></div>
                <small><?= __('excused') ?></small>
            </div>
        </div>
    </div>
    
    <!-- Attendance Table -->
    <div class="card">
        <div class="card-header"><?= __('attendance_history') ?> — <?= date('F Y', strtotime($selectedMonth . '-01')) ?></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th><?= __('date') ?></th><th>Day</th><th><?= __('status') ?></th><th><?= __('reason') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $startDate = $selectedMonth . '-01';
                        $daysInMonth = date('t', strtotime($startDate));
                        for ($d = 1; $d <= $daysInMonth; $d++):
                            $dateStr = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                            $dayOfWeek = date('N', strtotime($dateStr));
                            if ($dayOfWeek >= 6) continue; // Skip weekends
                            if (strtotime($dateStr) > time()) continue; // Skip future dates
                            $rec = $attendanceData[$dateStr] ?? null;
                        ?>
                        <tr>
                            <td><?= formatDate($dateStr, 'M d, Y') ?></td>
                            <td><?= date('l', strtotime($dateStr)) ?></td>
                            <td>
                                <?php if ($rec): ?>
                                <span class="badge <?= getAttendanceBadgeClass($rec['status']) ?>"><?= __(e($rec['status'])) ?></span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= e($rec['reason'] ?? '') ?></small></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
