<?php
require_once __DIR__ . '/../init.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
header('Location: ' . APP_BASE_URL . '/group-session-slot.php?id=' . $id, true, 301);
exit;
