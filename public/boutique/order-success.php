<?php
// public/boutique/order-success.php – payment success callback for shop orders
require_once __DIR__ . '/../init.php';
Auth::requireLogin();

$orderId         = (int) ($_GET['order_id']        ?? 0);
$paymentIntentId = trim($_GET['payment_intent']    ?? '');
$isDemo          = isset($_GET['_demo']);

$orderModel = new ShopOrderModel();
$order      = $orderId ? $orderModel->findById($orderId) : null;

// Verify the order belongs to the current user
if (!$order || (int) $order['user_id'] !== Auth::currentUserId()) {
    flash('error', 'Commande introuvable.');
    header('Location: ' . APP_BASE_URL . '/boutique/');
    exit;
}

// Mark as paid if still pending
if ($order['status'] === 'pending') {
    $ref = $isDemo ? ('demo_shop_' . $orderId) : ($paymentIntentId ?: 'paid_shop_' . $orderId);
    $orderModel->markPaid($orderId, $ref);
    // Clear the shop cart
    Auth::start();
    $_SESSION['shop_cart'] = [];
}

$order = $orderModel->findById($orderId);
$items = $orderModel->getItems($orderId);

$pageTitle = 'Commande confirmée !';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>🎉 Commande confirmée !</h1>
        <p>Merci pour votre commande. Voici le récapitulatif.</p>
    </section>

    <div class="section-block" style="max-width:600px">
        <h2>Commande #<?= (int) $orderId ?></h2>
        <p>
            <strong>Livraison :</strong> <?= e(shopDeliveryLabel($order['delivery_method'])) ?><br>
            <strong>Date :</strong> <?= e(formatDate($order['delivery_date'])) ?><br>
            <?php if (in_array($order['delivery_method'], ['market_wednesday', 'market_friday'], true)): ?>
                <strong>Retrait marché :</strong> entre 8h30 et 12h30<br>
            <?php endif; ?>
            <?php if ($order['delivery_method'] === 'home' && !empty($order['delivery_address'])): ?>
                <strong>Adresse :</strong> <?= nl2br(e($order['delivery_address'])) ?><br>
            <?php endif; ?>
        </p>

        <table style="width:100%;margin-top:1rem">
            <thead>
                <tr><th>Produit</th><th>Qté</th><th style="text-align:right">Sous-total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td style="text-align:right"><?= formatPrice((int) $item['unit_price_cents'] * (int) $item['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ((int) $order['delivery_fee_cents'] > 0): ?>
                    <tr>
                        <td colspan="2" style="color:var(--color-muted)">Frais de livraison</td>
                        <td style="text-align:right"><?= formatPrice((int) $order['delivery_fee_cents']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr style="font-weight:bold;border-top:2px solid var(--color-border)">
                    <td colspan="2">Total payé</td>
                    <td style="text-align:right"><?= formatPrice((int) $order['total_cents']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap">
        <a href="<?= APP_BASE_URL ?>/boutique/my-orders.php" class="btn btn--secondary">📋 Mes commandes</a>
        <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--primary">🛍️ Retour au catalogue</a>
    </div>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
