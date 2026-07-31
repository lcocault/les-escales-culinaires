<?php
// public/boutique/order-cancel.php – payment cancelled for shop orders
require_once __DIR__ . '/../init.php';
Auth::requireLogin();

$orderId    = (int) ($_GET['order_id'] ?? 0);
$orderModel = new ShopOrderModel();
$order      = $orderId ? $orderModel->findById($orderId) : null;

// Delete the pending order (cancels it before payment confirmation)
if ($order && (int) $order['user_id'] === Auth::currentUserId()) {
    $orderModel->deletePending($orderId);
}

$pageTitle = 'Commande annulée';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <section class="hero">
        <h1>😕 Paiement annulé</h1>
        <p>Votre commande n'a pas été confirmée. Votre panier a été conservé.</p>
    </section>

    <div style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap">
        <a href="<?= APP_BASE_URL ?>/boutique/cart.php" class="btn btn--primary">🛒 Retour au panier</a>
        <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--secondary">🛍️ Retour au catalogue</a>
    </div>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
