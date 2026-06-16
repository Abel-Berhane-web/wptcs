<?php
/**
 * Admin - Users Management
 */
requireRole('admin');

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$pageTitle = __('manage_users');

// ═══ Handle Create/Edit User ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        setFlashMessage('danger', __('csrf_error'));
        header('Location: ' . buildUrl('admin/users'));
        exit;
    }

    $userId = intval($_POST['user_id'] ?? 0);
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    // Validation
    $errors = [];
    if (empty($username))
        $errors[] = __('field_required') . ' (Username)';
    if (empty($email))
        $errors[] = __('field_required') . ' (Email)';
    if (empty($firstName))
        $errors[] = __('field_required') . ' (First Name)';
    if (empty($lastName))
        $errors[] = __('field_required') . ' (Last Name)';
    if (!in_array($role, ['admin', 'principal', 'teacher', 'parent']))
        $errors[] = 'Invalid role';

    if (empty($errors)) {
        try {
            if ($userId > 0) {
                // Update user
                $sql = "UPDATE users SET username=:username, email=:email, first_name=:fname, last_name=:lname, role=:role, phone=:phone, gender=:gender, is_active=:active WHERE user_id=:id";
                $params = [':username' => $username, ':email' => $email, ':fname' => $firstName, ':lname' => $lastName, ':role' => $role, ':phone' => $phone, ':gender' => $gender, ':active' => $isActive, ':id' => $userId];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                if (!empty($password)) {
                    $stmt = $pdo->prepare("UPDATE users SET password=:pass WHERE user_id=:id");
                    $stmt->execute([':pass' => hashPassword($password), ':id' => $userId]);
                }

                logAudit(getCurrentUserId(), 'update_user', 'users', $userId);
                setFlashMessage('success', __('user_updated'));
            } else {
                // Create new user
                if (empty($password))
                    $errors[] = __('field_required') . ' (Password)';

                if (empty($errors)) {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, first_name, last_name, role, gender, is_active) VALUES (:username, :pass, :email, :phone, :fname, :lname, :role, :gender, :active)");
                    $stmt->execute([
                        ':username' => $username,
                        ':pass' => hashPassword($password),
                        ':email' => $email,
                        ':phone' => $phone,
                        ':fname' => $firstName,
                        ':lname' => $lastName,
                        ':role' => $role,
                        ':gender' => $gender,
                        ':active' => $isActive
                    ]);

                    $newId = $pdo->lastInsertId();
                    logAudit(getCurrentUserId(), 'create_user', 'users', $newId);

                    // Send welcome email with login credentials
                    $emailSent = sendWelcomeEmail($email, $firstName, $lastName, $username, $password, $role);
                    if ($emailSent) {
                        setFlashMessage('success', __('user_created') . ' — Login credentials sent to ' . e($email));
                    } else {
                        setFlashMessage('success', __('user_created') . ' — Email could not be sent, please share credentials manually.');
                    }
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $msg = str_contains($e->getMessage(), 'username') ? __('username_taken') : __('email_taken');
                setFlashMessage('danger', $msg);
            } else {
                setFlashMessage('danger', __('error') . ': ' . $e->getMessage());
            }
        }
    } else {
        setFlashMessage('danger', implode('<br>', $errors));
    }

    header('Location: ' . buildUrl('admin/users'));
    exit;
}

// ═══ Handle Delete ═══
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id !== getCurrentUserId()) {
        $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = :id")->execute([':id' => $id]);
        logAudit(getCurrentUserId(), 'deactivate_user', 'users', $id);
        setFlashMessage('success', __('user_deleted'));
    }
    header('Location: ' . buildUrl('admin/users'));
    exit;
}

// ═══ Load data for edit ═══
$editUser = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->execute([':id' => intval($_GET['id'])]);
    $editUser = $stmt->fetch();
}

// ═══ Filters ═══
$filterRole = $_GET['role_filter'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$page_num = max(1, intval($_GET['p'] ?? 1));

$where = "WHERE 1=1";
$params = [];

if ($filterRole) {
    $where .= " AND role = :role";
    $params[':role'] = $filterRole;
}
if ($searchTerm) {
    $where .= " AND (first_name LIKE :search OR last_name LIKE :search2 OR username LIKE :search3 OR email LIKE :search4)";
    $params[':search'] = "%$searchTerm%";
    $params[':search2'] = "%$searchTerm%";
    $params[':search3'] = "%$searchTerm%";
    $params[':search4'] = "%$searchTerm%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$pagination = paginate($totalRecords, $page_num);

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v)
    $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

include INCLUDES_PATH . '/header.php';
?>

<div class="fade-in-up">
    <?php if ($action === 'create' || $action === 'edit'): ?>
        <!-- ═══ Create/Edit User Form ═══ -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i
                        class="bi bi-person-<?= $action === 'edit' ? 'gear' : 'plus' ?> me-2"></i><?= $editUser ? __('edit_user') : __('create_user') ?></span>
                <a href="<?= buildUrl('admin/users') ?>" class="btn btn-sm btn-outline-secondary"><i
                        class="bi bi-arrow-left me-1"></i><?= __('back') ?></a>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= buildUrl('admin/users') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?? 0 ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('first_name') ?> *</label>
                            <input type="text" class="form-control" name="first_name"
                                value="<?= e($editUser['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('last_name') ?> *</label>
                            <input type="text" class="form-control" name="last_name"
                                value="<?= e($editUser['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('username') ?> *</label>
                            <input type="text" class="form-control" name="username"
                                value="<?= e($editUser['username'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('email') ?> *</label>
                            <input type="email" class="form-control" name="email" value="<?= e($editUser['email'] ?? '') ?>"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('role') ?> *</label>
                            <select class="form-select" name="role" required>
                                <option value=""><?= __('select') ?>...</option>
                                <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                    <?= __('admin') ?>
                                </option>
                                <option value="principal" <?= ($editUser['role'] ?? '') === 'principal' ? 'selected' : '' ?>>
                                    <?= __('principal') ?>
                                </option>
                                <option value="teacher" <?= ($editUser['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>
                                    <?= __('teacher') ?>
                                </option>
                                <option value="parent" <?= ($editUser['role'] ?? '') === 'parent' ? 'selected' : '' ?>>
                                    <?= __('parent') ?>
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('gender') ?></label>
                            <select class="form-select" name="gender">
                                <option value=""><?= __('select') ?>...</option>
                                <option value="male" <?= ($editUser['gender'] ?? '') === 'male' ? 'selected' : '' ?>>
                                    <?= __('male') ?>
                                </option>
                                <option value="female" <?= ($editUser['gender'] ?? '') === 'female' ? 'selected' : '' ?>>
                                    <?= __('female') ?>
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('phone') ?></label>
                            <input type="text" class="form-control" name="phone" value="<?= e($editUser['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('password') ?>
                                <?= $editUser ? '(' . __('optional') . ')' : '*' ?></label>
                            <input type="password" class="form-control" name="password" <?= !$editUser ? 'required' : '' ?>>
                            <?php if ($editUser): ?><small class="text-muted">Leave blank to keep current
                                    password</small><?php endif; ?>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                    <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive"><?= __('active') ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i
                                class="bi bi-check-lg me-2"></i><?= __('save') ?></button>
                        <a href="<?= buildUrl('admin/users') ?>"
                            class="btn btn-outline-secondary ms-2"><?= __('cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- ═══ Users List ═══ -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0"><?= __('user_list') ?></h5>
            <a href="<?= buildUrl('admin/users', ['action' => 'create']) ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i><?= __('create_user') ?>
            </a>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" class="d-flex gap-3 align-items-end flex-wrap w-100">
                <input type="hidden" name="page" value="admin/users">
                <div class="form-group">
                    <label><?= __('role') ?></label>
                    <select class="form-select form-select-sm" name="role_filter" onchange="this.form.submit()">
                        <option value=""><?= __('all') ?></option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>><?= __('admin') ?></option>
                        <option value="principal" <?= $filterRole === 'principal' ? 'selected' : '' ?>><?= __('principal') ?>
                        </option>
                        <option value="teacher" <?= $filterRole === 'teacher' ? 'selected' : '' ?>><?= __('teacher') ?>
                        </option>
                        <option value="parent" <?= $filterRole === 'parent' ? 'selected' : '' ?>><?= __('parent') ?></option>
                    </select>
                </div>
                <div class="form-group flex-grow-1">
                    <label><?= __('search') ?></label>
                    <input type="text" class="form-control form-control-sm" name="search" value="<?= e($searchTerm) ?>"
                        placeholder="Search by name, username, email...">
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><i
                        class="bi bi-search me-1"></i><?= __('search') ?></button>
            </form>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= __('name') ?></th>
                                <th><?= __('username') ?></th>
                                <th><?= __('email') ?></th>
                                <th><?= __('role') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('last_login') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $i => $user): ?>
                                <tr>
                                    <td><?= $pagination['offset'] + $i + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="user-avatar" style="width:32px;height:32px;">
                                                <div class="avatar-initials" style="font-size:11px;">
                                                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">
                                                    <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                                </div>
                                                <small class="text-muted"><?= e($user['phone'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?= e($user['username']) ?></code></td>
                                    <td><?= e($user['email']) ?></td>
                                    <td><span
                                            class="badge bg-<?= match ($user['role']) { 'admin' => 'danger', 'principal' => 'warning', 'teacher' => 'primary', 'parent' => 'success'} ?>"><?= __(e($user['role'])) ?></span>
                                    </td>
                                    <td><span
                                            class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>"><?= $user['is_active'] ? __('active') : __('inactive') ?></span>
                                    </td>
                                    <td><small><?= $user['last_login'] ? timeAgo($user['last_login']) : '-' ?></small></td>
                                    <td>
                                        <a href="<?= buildUrl('admin/users', ['action' => 'edit', 'id' => $user['user_id']]) ?>"
                                            class="btn btn-sm btn-outline-primary btn-icon" title="<?= __('edit') ?>">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($user['user_id'] !== getCurrentUserId()): ?>
                                            <a href="<?= buildUrl('admin/users', ['action' => 'delete', 'id' => $user['user_id']]) ?>"
                                                class="btn btn-sm btn-outline-danger btn-icon"
                                                data-confirm="<?= __('delete_confirm') ?>" title="<?= __('delete') ?>">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted"><?= __('no_data') ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?= renderPagination($pagination, buildUrl('admin/users', ['role_filter' => $filterRole, 'search' => $searchTerm])) ?>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>