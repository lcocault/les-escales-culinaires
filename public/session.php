<?php
// public/session.php – redirects to /ateliers/seance.php (new URL)
require_once __DIR__ . '/init.php';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$target = APP_BASE_URL . '/ateliers/seance.php' . ($id > 0 ? '?id=' . $id : '');
header('Location: ' . $target, true, 301);
exit;
