<?php
// public/admin/session-close.php – close a past session (POST only)
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
    exit;
}

Auth::verifyCsrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    flash('error', 'Séance introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
    exit;
}

$sessionModel = new SessionModel();
$session = $sessionModel->findById($id);

if (!$session) {
    flash('error', 'Séance introuvable.');
} elseif ($sessionModel->closePastSession($id)) {
    flash('success', 'Séance clôturée.');
} else {
    flash('error', 'Cette séance ne peut pas être clôturée.');
}

header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
exit;
