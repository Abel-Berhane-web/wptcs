<?php
/**
 * Logout
 */
requireLogin();
logAudit(getCurrentUserId(), 'logout', 'users', getCurrentUserId());
destroySession();
initSession();
setFlashMessage('success', __('logout_success'));
header('Location: ' . BASE_URL . '/index.php?page=login');
exit;
