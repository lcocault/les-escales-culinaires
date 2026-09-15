<?php
// public/promo-validate.php – lightweight JSON endpoint for live promo-code validation
require_once __DIR__ . '/init.php';
Auth::requireLogin();

header('Content-Type: application/json');

$code      = strtoupper(trim($_GET['code'] ?? ''));
$sessionId = (int) ($_GET['session_id'] ?? 0);
$childCount = max(1, (int) ($_GET['child_count'] ?? 1));

if ($code === '' || $sessionId <= 0) {
    echo json_encode(['valid' => false, 'message' => 'Paramètres manquants.']);
    exit;
}

$promoModel = new PromoCodeModel();
$promo      = $promoModel->validateForSession($code, $sessionId);

if ($promo === null) {
    echo json_encode(['valid' => false, 'message' => 'Code invalide ou non applicable à cette séance.']);
    exit;
}

echo json_encode([
    'valid'          => true,
    'discount_cents' => (int) $promo['discount_cents'] * (
        $promo['max_uses'] === null
            ? $childCount
            : min($childCount, max(0, (int) $promo['max_uses'] - (int) $promo['used_count']))
    ),
    'message'        => 'Code promotionnel valide.',
]);
