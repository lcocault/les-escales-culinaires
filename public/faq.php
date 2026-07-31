<?php
// public/faq.php – redirects to /ateliers/faq.php (new URL)
require_once __DIR__ . '/init.php';
header('Location: ' . APP_BASE_URL . '/ateliers/faq.php', true, 301);
exit;
