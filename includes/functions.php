<?php
/**
 * Helper Functions
 * WPTCS - Web-based Parent Teacher Communication System
 */

/**
 * Translation function - get string by key
 */
function __(string $key, array $params = []): string
{
    static $translations = null;

    if ($translations === null) {
        $lang = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
        $langFile = LANG_PATH . '/' . $lang . '.php';

        if (file_exists($langFile)) {
            $translations = require $langFile;
        } else {
            $translations = require LANG_PATH . '/en.php';
        }
    }

    $text = $translations[$key] ?? $key;

    // Replace parameters
    foreach ($params as $param => $value) {
        $text = str_replace(':' . $param, $value, $text);
    }

    return $text;
}

/**
 * Reset translations cache (used when switching language)
 */
function resetTranslations(): void
{
    // Force reload by clearing the static variable
    $GLOBALS['_translations_reset'] = true;
}

/**
 * Translate a subject name using the lang file key 'subj_SubjectName'
 * Falls back to the English DB name if no translation exists
 */
function translateSubject(string $subjectName): string
{
    $key = 'subj_' . $subjectName;
    $translated = __($key);
    // If __() returns the key itself, no translation exists — use original
    return ($translated === $key) ? $subjectName : $translated;
}

/**
 * Sanitize output for HTML
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input
 */
function sanitize(string $input): string
{
    return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
}

/**
 * Get current academic year ID
 */
function getCurrentAcademicYearId(): ?int
{
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT year_id FROM academic_years WHERE is_current = 1 AND status = 'active' LIMIT 1");
    $result = $stmt->fetch();
    return $result ? (int) $result['year_id'] : null;
}

/**
 * Get current academic year name
 */
function getCurrentAcademicYearName(): ?string
{
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT year_name FROM academic_years WHERE is_current = 1 AND status = 'active' LIMIT 1");
    $result = $stmt->fetch();
    return $result ? $result['year_name'] : null;
}

/**
 * Log audit trail
 */
function logAudit(int $userId, string $action, ?string $tableName = null, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): void
{
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip, :ua)");
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':table_name' => $tableName,
            ':record_id' => $recordId,
            ':old_values' => $oldValues ? json_encode($oldValues) : null,
            ':new_values' => $newValues ? json_encode($newValues) : null,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Create notification
 */
function createNotification(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): void
{
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (:user_id, :title, :message, :type, :link)");
        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':message' => $message,
            ':type' => $type,
            ':link' => $link
        ]);
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
    }
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount(int $userId): int
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt->execute([':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get unread comments count
 */
function getUnreadCommentsCount(int $userId): int
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE receiver_id = :user_id AND is_read = 0");
    $stmt->execute([':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Upload file with validation
 */
function uploadFile(array $file, string $destination, array $allowedExtensions = []): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => __('file_too_large')];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = !empty($allowedExtensions) ? $allowedExtensions : ALLOWED_EXTENSIONS;

    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => __('invalid_file_type')];
    }

    $filename = uniqid('file_', true) . '.' . $ext;
    $filepath = $destination . '/' . $filename;

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $filepath];
    }

    return ['success' => false, 'message' => 'Failed to move uploaded file.'];
}

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'M d, Y'): string
{
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime(string $datetime, string $format = 'M d, Y h:i A'): string
{
    return date($format, strtotime($datetime));
}

/**
 * Get time ago string
 */
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);

    if ($diff < 60)
        return 'just now';
    if ($diff < 3600)
        return floor($diff / 60) . ' min ago';
    if ($diff < 86400)
        return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800)
        return floor($diff / 86400) . ' days ago';

    return formatDate($datetime);
}

/**
 * Generate star rating HTML
 */
function starRating(int $rating, int $max = 5): string
{
    $html = '<span class="star-rating">';
    for ($i = 1; $i <= $max; $i++) {
        $html .= $i <= $rating ? '★' : '☆';
    }
    $html .= '</span>';
    return $html;
}

/**
 * Get grade display name
 */
function getGradeDisplayName(string $gradeName, string $sectionName = ''): string
{
    if (str_starts_with($gradeName, 'KG')) {
        $display = $gradeName; // KG1, KG2, KG3
    } else {
        $display = __('grade') . ' ' . $gradeName;
    }
    if ($sectionName) {
        $display .= '-' . $sectionName;
    }
    return $display;
}

/**
 * Generate student code
 */
function generateStudentCode(): string
{
    $pdo = getDBConnection();
    $year = date('Y');
    $prefix = 'FTBLM-' . $year . '-';
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(student_code, LENGTH(:prefix)+1) AS UNSIGNED)) as max_num FROM students WHERE student_code LIKE :like");
    $stmt->execute([':prefix' => $prefix, ':like' => $prefix . '%']);
    $result = $stmt->fetch();
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

/**
 * Get the next grade (for promotion)
 * Returns null if already at Grade 8 (graduating)
 */
function getNextGrade(int $currentGradeId): ?array
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT grade_order FROM grades WHERE grade_id = :gid");
    $stmt->execute([':gid' => $currentGradeId]);
    $current = $stmt->fetch();
    if (!$current)
        return null;

    $stmt = $pdo->prepare("SELECT grade_id, grade_name FROM grades WHERE grade_order = :next AND status = 'active' LIMIT 1");
    $stmt->execute([':next' => $current['grade_order'] + 1]);
    return $stmt->fetch() ?: null;
}

/**
 * Calculate student average for a given year and semester
 */
function calculateStudentAverage(int $studentId, int $yearId, ?string $semester = null): float
{
    $pdo = getDBConnection();
    $sql = "SELECT m.subject_id, SUM(m.score * at.weight / at.max_score) as weighted_score
            FROM marks m JOIN assessment_types at ON m.assessment_type_id = at.type_id
            WHERE m.student_id = :sid AND m.academic_year_id = :yid";
    $params = [':sid' => $studentId, ':yid' => $yearId];
    if ($semester) {
        $sql .= " AND m.semester = :sem";
        $params[':sem'] = $semester;
    }
    $sql .= " GROUP BY m.subject_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subjects = $stmt->fetchAll();
    if (empty($subjects))
        return 0;
    $total = array_sum(array_column($subjects, 'weighted_score'));
    return round($total / count($subjects), 1);
}

/**
 * Generate CSV content from array
 */
function generateCSV(array $headers, array $rows): string
{
    $output = fopen('php://temp', 'r+');
    // BOM for Excel UTF-8 compatibility
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

/**
 * Parse CSV upload into array of rows
 */
function parseCSVUpload(array $file): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        return ['success' => false, 'message' => 'Only CSV files are allowed'];
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'Cannot read file'];
    }

    // Skip BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return ['success' => false, 'message' => 'Empty file'];
    }
    // Trim headers
    $headers = array_map('trim', $headers);

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === count($headers)) {
            $rows[] = array_combine($headers, array_map('trim', $row));
        }
    }
    fclose($handle);

    return ['success' => true, 'headers' => $headers, 'rows' => $rows, 'count' => count($rows)];
}

/**
 * Simple pagination helper
 */
function paginate(int $totalRecords, int $currentPage = 1, int $perPage = RECORDS_PER_PAGE): array
{
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'per_page' => $perPage,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination(array $pagination, string $baseUrl): string
{
    if ($pagination['total_pages'] <= 1)
        return '';

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    // Previous
    $prevDisabled = !$pagination['has_prev'] ? ' disabled' : '';
    $prevPage = $pagination['current_page'] - 1;
    $html .= '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . $baseUrl . '&p=' . $prevPage . '">' . __('previous') . '</a></li>';

    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&p=1">1</a></li>';
        if ($start > 2)
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $baseUrl . '&p=' . $i . '">' . $i . '</a></li>';
    }

    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1)
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&p=' . $pagination['total_pages'] . '">' . $pagination['total_pages'] . '</a></li>';
    }

    // Next
    $nextDisabled = !$pagination['has_next'] ? ' disabled' : '';
    $nextPage = $pagination['current_page'] + 1;
    $html .= '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . $baseUrl . '&p=' . $nextPage . '">' . __('next') . '</a></li>';

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Check if marks can still be edited (within 48 hours)
 */
function canEditMarks(string $createdAt): bool
{
    return (time() - strtotime($createdAt)) < MARK_EDIT_WINDOW;
}

/**
 * Get attendance status badge class
 */
function getAttendanceBadgeClass(string $status): string
{
    return match ($status) {
        'present' => 'bg-success',
        'absent' => 'bg-danger',
        'late' => 'bg-warning text-dark',
        'excused' => 'bg-info',
        default => 'bg-secondary'
    };
}

/**
 * Generate random password
 */
function generateRandomPassword(int $length = 10): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Hash password
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Get the current language
 */
function getCurrentLanguage(): string
{
    return $_SESSION['language'] ?? DEFAULT_LANGUAGE;
}

/**
 * Build URL with query parameters
 */
function buildUrl(string $page, array $params = []): string
{
    $url = BASE_URL . '/index.php?page=' . $page;
    foreach ($params as $key => $value) {
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    return $url;
}

/**
 * Send an email using PHPMailer (centralized SMTP config)
 * All email sending in the app goes through this single functionfunction sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool
{
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        error_log('PHPMailer not installed. Cannot send email.');
        return false;
    }
    require_once $vendorAutoload;

    // Load SMTP settings dynamically
    $smtpConfig = __DIR__ . '/../config/smtp.php';
    if (file_exists($smtpConfig)) {
        require_once $smtpConfig;
    }

    // Fallbacks if SMTP config does not define constants
    if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
    if (!defined('SMTP_AUTH')) define('SMTP_AUTH', true);
    if (!defined('SMTP_USER')) define('SMTP_USER', '');
    if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
    if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');
    if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
    if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', SMTP_USER);
    if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Felege Tibeb Academy');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->CharSet = 'UTF-8';

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome email with login credentials to a newly registered user
 */
function sendWelcomeEmail(string $email, string $firstName, string $lastName, string $username, string $plainPassword, string $role): bool
{
    $loginUrl = BASE_URL_ABS . '/index.php?page=login';
    $logoUrl = BASE_URL_ABS . '/assets/img/beata_logo.png';
    $roleName = ucfirst($role);
    $year = date('Y');

    $htmlBody = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:linear-gradient(135deg,#1a237e,#0d47a1);padding:30px;text-align:center;border-radius:12px 12px 0 0;'>
            <img src='$logoUrl' alt='Felege Tibeb Academy Logo' style='max-height:80px;margin-bottom:10px;' />
            <h1 style='color:#fff;margin:0;font-size:22px;'>ፈለገ ጥበብ በዓታ ለማርያም አካዳሚ</h1>
            <p style='color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:13px;'>Felege Tibeb Beata LeMariam Academy</p>
        </div>
        <div style='padding:30px;background:#fff;border:1px solid #e0e0e0;'>
            <h2 style='color:#1a237e;margin-top:0;'>Welcome, $firstName!</h2>
            <p>Your account has been created on the <strong>" . APP_NAME . "</strong> system. You can now log in using the credentials below:</p>
            <div style='background:#f5f5f5;border-radius:8px;padding:20px;margin:20px 0;border-left:4px solid #1a237e;'>
                <p style='margin:8px 0;'><strong>Role:</strong> $roleName</p>
                <p style='margin:8px 0;'><strong>Username:</strong> <code style='background:#e8eaf6;padding:2px 8px;border-radius:4px;'>$username</code></p>
                <p style='margin:8px 0;'><strong>Password:</strong> <code style='background:#e8eaf6;padding:2px 8px;border-radius:4px;'>$plainPassword</code></p>
            </div>
            <p><a href='$loginUrl' style='display:inline-block;background:#1a237e;color:#fff;padding:12px 30px;text-decoration:none;border-radius:8px;font-weight:bold;'>Login Now</a></p>
            <p style='color:#757575;font-size:13px;margin-top:20px;'>Please change your password after your first login for security.</p>
        </div>
        <div style='background:#f5f5f5;padding:15px;text-align:center;border-radius:0 0 12px 12px;border:1px solid #e0e0e0;border-top:0;'>
            <small style='color:#9e9e9e;'>&copy; $year Felege Tibeb Academy. All rights reserved.</small>
        </div>
    </div>";

    $altBody = "Welcome $firstName!\n\nYour account has been created.\nRole: $roleName\nUsername: $username\nPassword: $plainPassword\n\nLogin at: $loginUrl\n\nPlease change your password after first login.";

    return sendMail($email, "$firstName $lastName", 'Welcome to ' . APP_NAME . ' - Your Login Credentials', $htmlBody, $altBody);
}
