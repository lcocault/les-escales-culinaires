<?php
// public/sessions.php – redirects to /ateliers/ (new URL)
require_once __DIR__ . '/init.php';
header('Location: ' . APP_BASE_URL . '/ateliers/', true, 301);
exit;
