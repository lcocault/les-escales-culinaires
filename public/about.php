<?php
// public/about.php – redirects to /ateliers/concept.php (new URL)
require_once __DIR__ . '/init.php';
header('Location: ' . APP_BASE_URL . '/ateliers/concept.php', true, 301);
exit;
