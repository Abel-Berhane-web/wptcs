<?php
/**
 * Shared - Comments (Parent-Teacher Communication)
 * Comments are linked to specific students
 */
requireLogin();

$pdo = getDBConnection();
$userId = getCurrentUserId();
$role = getCurrentUserRole();
$pageTitle = __('comments');

$studentId = intval($_GET['student_id'] ?? 0);
$receiverId = intval($_GET['receiver_id'] ?? 0);

// ─── REAL-TIME POLLING ENDPOINT ───
// Called by JS every 3 seconds to fetch new messages
if (isset($_GET['action']) && $_GET['action'] === 'poll_comments') {
    header('Content-Type: application/json');
    if (!$studentId || !$receiverId) {
        echo json_encode(['messages' => []]);
        exit;
    }
    $since = $_GET['since'] ?? '1970-01-01 00:00:00';
    // Sanitize: ensure it's a valid datetime string
    if (!strtotime($since)) $since = '1970-01-01 00:00:00';
    
    $stmt = $pdo->prepare("
        SELECT c.comment_id, c.sender_id, c.message, c.created_at,
               CONCAT(u.first_name, ' ', u.last_name) as sender_name
        FROM comments c
        JOIN users u ON c.sender_id = u.user_id
        WHERE c.student_id = :sid
          AND (
              (c.sender_id = :uid  AND c.receiver_id = :other)
           OR (c.sender_id = :other2 AND c.receiver_id = :uid2)
          )
          AND c.created_at > :since
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([
        ':sid'    => $studentId,
        ':uid'    => $userId,
        ':other'  => $receiverId,
        ':other2' => $receiverId,
        ':uid2'   => $userId,
        ':since'  => $since
    ]);
    $newMessages = $stmt->fetchAll();
    
    // Mark incoming as read
    $pdo->prepare("UPDATE comments SET is_read = 1 WHERE student_id = :sid AND sender_id = :sender AND receiver_id = :uid AND is_read = 0")
        ->execute([':sid' => $studentId, ':sender' => $receiverId, ':uid' => $userId]);
    
    $formatted = [];
    foreach ($newMessages as $m) {
        $formatted[] = [
            'comment_id'  => $m['comment_id'],
            'sender_id'   => (int)$m['sender_id'],
            'message'     => $m['message'],
            'sender_name' => $m['sender_name'],
            'created_at'  => $m['created_at'],
            'time_ago'    => date('M d, Y h:i A', strtotime($m['created_at']))
        ];
    }
    echo json_encode(['messages' => $formatted]);
    exit;
}

// Handle AJAX comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => __('csrf_error')]);
        exit;
    }
    
    $message = trim($_POST['message'] ?? '');
    $studentId = intval($_POST['student_id'] ?? 0);
    $receiverId = intval($_POST['receiver_id'] ?? 0);
    
    if (empty($message) || !$studentId || !$receiverId) {
        echo json_encode(['success' => false, 'message' => __('field_required')]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO comments (student_id, sender_id, receiver_id, message) VALUES (:sid, :sender, :receiver, :msg)");
        $stmt->execute([':sid' => $studentId, ':sender' => $userId, ':receiver' => $receiverId, ':msg' => $message]);
        
        createNotification($receiverId, __('comment'), substr($message, 0, 50) . '...', 'info', buildUrl('shared/comments', ['student_id' => $studentId]));
        
        echo json_encode([
            'success' => true,
            'message' => __('message_sent'),
            'comment' => [
                'message' => $message,
                'time' => date('M d, Y h:i A')
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => __('error')]);
    }
    exit;
}

// Mark messages as read
if ($studentId && $receiverId) {
    $pdo->prepare("UPDATE comments SET is_read = 1 WHERE student_id = :sid AND sender_id = :sender AND receiver_id = :uid AND is_read = 0")
        ->execute([':sid' => $studentId, ':sender' => $receiverId, ':uid' => $userId]);
}

// Get student list based on role
$studentsList = [];
if ($role === 'parent') {
    $stmt = $pdo->prepare("
        SELECT st.student_id, st.first_name, st.last_name, st.section_id, sec.section_name, g.grade_name,
               sec.homeroom_teacher_id, CONCAT(ht.first_name, ' ', ht.last_name) as teacher_name
        FROM students st
        LEFT JOIN sections sec ON st.section_id = sec.section_id
        LEFT JOIN grades g ON sec.grade_id = g.grade_id
        LEFT JOIN users ht ON sec.homeroom_teacher_id = ht.user_id
        WHERE st.parent_id = :pid AND st.status = 'active'
    ");
    $stmt->execute([':pid' => $userId]);
    $studentsList = $stmt->fetchAll();
} elseif ($role === 'teacher') {
    $currentYear = getCurrentAcademicYearId();
    // Students from homeroom classes and subject classes
    $stmt = $pdo->prepare("
        SELECT DISTINCT st.student_id, st.first_name, st.last_name, st.parent_id,
               sec.section_name, g.grade_name, CONCAT(p.first_name, ' ', p.last_name) as parent_name
        FROM students st
        JOIN sections sec ON st.section_id = sec.section_id
        JOIN grades g ON sec.grade_id = g.grade_id
        LEFT JOIN users p ON st.parent_id = p.user_id
        WHERE st.status = 'active' AND (
            sec.homeroom_teacher_id = :tid
            OR sec.section_id IN (SELECT section_id FROM teacher_subjects WHERE teacher_id = :tid2 AND academic_year_id = :yid)
        )
        ORDER BY g.grade_order, sec.section_name, st.first_name
    ");
    $stmt->execute([':tid' => $userId, ':tid2' => $userId, ':yid' => $currentYear]);
    $studentsList = $stmt->fetchAll();
}

// Get conversation messages
$messages = [];
if ($studentId) {
    $otherUserId = $receiverId;
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name
        FROM comments c
        JOIN users u ON c.sender_id = u.user_id
        WHERE c.student_id = :sid AND (
            (c.sender_id = :uid AND c.receiver_id = :other) OR
            (c.sender_id = :other2 AND c.receiver_id = :uid2)
        )
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([':sid' => $studentId, ':uid' => $userId, ':other' => $otherUserId, ':other2' => $otherUserId, ':uid2' => $userId]);
    $messages = $stmt->fetchAll();
}

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <h5 class="mb-4"><i class="bi bi-chat-dots me-2"></i><?= __('comments') ?></h5>
    
    <div class="row g-4">
        <!-- Student/Conversation List -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?= __('conversations') ?></div>
                <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                    <div class="list-group list-group-flush">
                        <?php foreach ($studentsList as $s): 
                            $contactId = ($role === 'parent') ? ($s['homeroom_teacher_id'] ?? 0) : ($s['parent_id'] ?? 0);
                            $contactName = ($role === 'parent') ? ($s['teacher_name'] ?? 'Teacher') : ($s['parent_name'] ?? 'Parent');
                            if (!$contactId) continue;
                            $isActive = ($studentId == $s['student_id'] && $receiverId == $contactId);
                            
                            // Unread count
                            $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE student_id = :sid AND sender_id = :sender AND receiver_id = :uid AND is_read = 0");
                            $unreadStmt->execute([':sid' => $s['student_id'], ':sender' => $contactId, ':uid' => $userId]);
                            $unread = $unreadStmt->fetchColumn();
                        ?>
                        <a href="<?= buildUrl('shared/comments', ['student_id' => $s['student_id'], 'receiver_id' => $contactId]) ?>" 
                           class="list-group-item list-group-item-action <?= $isActive ? 'active' : '' ?>">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                    <small class="<?= $isActive ? 'text-white-50' : 'text-muted' ?>">
                                        <?= e($contactName) ?>
                                        <?php if ($s['grade_name'] ?? null): ?>
                                         — <?= e(($s['grade_name']==='KG'?'KG':'Gr.'.$s['grade_name']).'-'.$s['section_name']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php if ($unread > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $unread ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        
                        <?php if (empty($studentsList)): ?>
                        <div class="p-3 text-center text-muted"><?= __('no_data') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="col-lg-8">
            <?php if ($studentId && $receiverId): ?>
            <div class="card">
                <div class="card-header">
                    <?php 
                    $stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE student_id = :sid");
                    $stmt->execute([':sid' => $studentId]);
                    $chatStudent = $stmt->fetch();
                    $stmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE user_id = :uid");
                    $stmt->execute([':uid' => $receiverId]);
                    $chatPartner = $stmt->fetch();
                    ?>
                    <i class="bi bi-chat-dots me-2"></i>
                    <?= e(($chatStudent['first_name'] ?? '') . ' ' . ($chatStudent['last_name'] ?? '')) ?> — 
                    <small class="text-muted"><?= e(($chatPartner['first_name'] ?? '') . ' ' . ($chatPartner['last_name'] ?? '')) ?> (<?= __(e($chatPartner['role'] ?? '')) ?>)</small>
                </div>
                
                <!-- Messages -->
<?php
    $lastMsgTime = !empty($messages) ? end($messages)['created_at'] : '1970-01-01 00:00:00';
?>
                <div class="comment-thread" id="commentThread"
                     data-student-id="<?= $studentId ?>"
                     data-receiver-id="<?= $receiverId ?>"
                     data-current-user="<?= $userId ?>"
                     data-last-timestamp="<?= e($lastMsgTime) ?>"
                     data-poll-url="<?= e(buildUrl('shared/comments', ['student_id' => $studentId, 'receiver_id' => $receiverId, 'action' => 'poll_comments'])) ?>">
                    <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-4"><?= __('no_messages') ?></div>
                    <?php endif; ?>
                    
                    <?php foreach ($messages as $msg): ?>
                    <div class="comment-bubble <?= $msg['sender_id'] == $userId ? 'sent' : 'received' ?>">
                        <div><?= e($msg['message']) ?></div>
                        <div class="comment-meta">
                            <?= e($msg['sender_name']) ?> • <?= timeAgo($msg['created_at']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Message Input -->
                <div class="card-footer">
                    <form id="commentForm" action="<?= buildUrl('shared/comments') ?>" method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="student_id" value="<?= $studentId ?>">
                        <input type="hidden" name="receiver_id" value="<?= $receiverId ?>">
                        <div class="input-group">
                            <textarea class="form-control" name="message" placeholder="<?= __('write_comment') ?>" rows="1" required style="resize:none;"></textarea>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body empty-state">
                    <i class="bi bi-chat-dots"></i>
                    <h5>Select a conversation</h5>
                    <p>Choose a student from the list to start messaging.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
