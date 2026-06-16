<?php
/**
 * Parent - View Marks
 * Numeric scores only (0-100)
 */
requireRole('parent');

$pdo = getDBConnection();
$parentId = getCurrentUserId();
$currentYear = getCurrentAcademicYearId();
$pageTitle = __('view_marks');

$studentId = intval($_GET['student_id'] ?? 0);
$selectedSemester = $_GET['semester'] ?? '1';

// Get parent's children
$stmt = $pdo->prepare("SELECT student_id, first_name, last_name, section_id FROM students WHERE parent_id = :pid AND status = 'active'");
$stmt->execute([':pid' => $parentId]);
$children = $stmt->fetchAll();

// Validate student belongs to parent
$student = null;
$marks = [];
$assessmentTypes = $pdo->query("SELECT * FROM assessment_types WHERE status = 'active' ORDER BY type_id")->fetchAll();

if ($studentId) {
    $stmt = $pdo->prepare("SELECT st.*, sec.section_name, g.grade_name FROM students st LEFT JOIN sections sec ON st.section_id = sec.section_id LEFT JOIN grades g ON sec.grade_id = g.grade_id WHERE st.student_id = :sid AND st.parent_id = :pid");
    $stmt->execute([':sid' => $studentId, ':pid' => $parentId]);
    $student = $stmt->fetch();
    
    if ($student) {
        // Get all marks grouped by subject
        $stmt = $pdo->prepare("
            SELECT m.*, sub.subject_name, sub.subject_code, at.type_name, at.weight, at.max_score
            FROM marks m
            JOIN subjects sub ON m.subject_id = sub.subject_id
            JOIN assessment_types at ON m.assessment_type_id = at.type_id
            WHERE m.student_id = :sid AND m.academic_year_id = :yid AND m.semester = :sem
            ORDER BY sub.subject_name, at.type_id
        ");
        $stmt->execute([':sid' => $studentId, ':yid' => $currentYear, ':sem' => $selectedSemester]);
        $rawMarks = $stmt->fetchAll();
        
        // Group by subject
        foreach ($rawMarks as $m) {
            $marks[$m['subject_name']]['subject_code'] = $m['subject_code'];
            $marks[$m['subject_name']]['assessments'][$m['type_name']] = [
                'score' => $m['score'],
                'weight' => $m['weight'],
                'max_score' => $m['max_score']
            ];
        }
        
        // Calculate totals - scores are already out of their weight (e.g. Test1: 0-20, Final: 0-40)
        // So total = sum of all scores = out of 100
        foreach ($marks as $subName => &$subData) {
            $total = 0;
            foreach ($subData['assessments'] as $ass) {
                $total += floatval($ass['score']);
            }
            $subData['total'] = round($total, 1);
        }
        unset($subData);
    }
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-card-checklist me-2"></i><?= __('view_marks') ?></h5>
        <?php if ($student): ?>
        <div class="btn-group no-print">
            <button id="printReportCard" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i><?= __('print') ?></button>
            <button id="downloadPdf" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Filter -->
    <div class="filter-bar no-print">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
            <input type="hidden" name="page" value="parent/view-marks">
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
                <label><?= __('semester') ?></label>
                <select class="form-select form-select-sm" name="semester" onchange="this.form.submit()">
                    <option value="1" <?= $selectedSemester === '1' ? 'selected' : '' ?>><?= __('semester_1') ?></option>
                    <option value="2" <?= $selectedSemester === '2' ? 'selected' : '' ?>><?= __('semester_2') ?></option>
                </select>
            </div>
        </form>
    </div>
    
    <?php if ($student && !empty($marks)): ?>
    <!-- Report Card Header -->
    <div class="card" id="reportCardPrintArea">
        <div class="report-card-header text-center pb-3 pt-3">
            <img src="<?= BASE_URL ?>/assets/img/beata_logo.png" alt="School Logo" style="max-height: 80px; margin-bottom: 15px; object-fit: contain;">
            <div style="line-height: 1.3;">
                <div style="font-size: 1.3rem; color: #1e1e2d; font-weight: 800; font-family: 'Noto Sans Ethiopic', sans-serif;">ፈለገ ጥበብ በዓታ ለማርያም አካዳሚ</div>
                <div style="font-size: 1rem; color: #6c757d; font-weight: 600; margin-top: 4px;">Felege Tibeb Beata LeMariam Academy</div>
                <div style="font-size: 0.85rem; color: #a1a5b7; margin-top: 5px;"><?= e(SCHOOL_LOCATION) ?></div>
            </div>
            <hr>
            <div class="row text-start">
                <div class="col-md-4"><strong><?= __('student_name') ?>:</strong> <?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
                <div class="col-md-4"><strong><?= __('grade') ?>/<?= __('section') ?>:</strong> <?= e(($student['grade_name']==='KG'?'KG':'Grade '.$student['grade_name']).'-'.$student['section_name']) ?></div>
                <div class="col-md-4"><strong><?= __('semester') ?>:</strong> <?= __('semester_' . $selectedSemester) ?></div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th><?= __('subject') ?></th>
                            <?php foreach ($assessmentTypes as $at): ?>
                            <th class="text-center"><?= e($at['type_name']) ?><br><small>(<?= intval($at['max_score']) ?>)</small></th>
                            <?php endforeach; ?>
                            <th class="text-center fw-bold"><?= __('total_score') ?><br><small>(100%)</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grandTotal = 0;
                        $subjectCount = 0;
                        foreach ($marks as $subName => $subData): 
                            $grandTotal += $subData['total'];
                            $subjectCount++;
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e(translateSubject($subName)) ?></td>
                            <?php foreach ($assessmentTypes as $at): ?>
                            <td class="text-center"><?= isset($subData['assessments'][$at['type_name']]) ? $subData['assessments'][$at['type_name']]['score'] : '-' ?></td>
                            <?php endforeach; ?>
                            <td class="text-center fw-bold <?= $subData['total'] >= 50 ? 'text-success' : 'text-danger' ?>">
                                <?= $subData['total'] ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <td class="fw-bold" colspan="<?= count($assessmentTypes) ?>"><?= __('average') ?></td>
                            <td class="text-center fw-bold fs-5"><?= $subjectCount ? round($grandTotal / $subjectCount, 1) : 0 ?>%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($student): ?>
    <div class="empty-state">
        <i class="bi bi-card-checklist"></i>
        <h5><?= __('no_marks_yet') ?></h5>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    document.getElementById('downloadPdf')?.addEventListener('click', function() {
        var element = document.getElementById('reportCardPrintArea');
        if(!element) return;
        
        var opt = {
            margin:       0.3,
            filename:     'Report_Card_<?= $student ? e($student['first_name'].'_'.$student['last_name']) : 'Student' ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        
        // Remove shadows/borders temporarily for a pristine PDF
        var originalShadow = element.style.boxShadow;
        var originalBorder = element.style.border;
        element.style.boxShadow = 'none';
        element.style.border = 'none';

        html2pdf().set(opt).from(element).save().then(function() {
            // Restore styles
            element.style.boxShadow = originalShadow;
            element.style.border = originalBorder;
        });
    });
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
