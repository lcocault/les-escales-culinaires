<?php
// public/boutique/order-detail.php – customer view of a single shop order
require_once __DIR__ . '/../init.php';
Auth::requireLogin();

$orderModel = new ShopOrderModel();
$id         = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$order      = $id ? $orderModel->findById($id) : null;

if (!$order || (int) $order['user_id'] !== Auth::currentUserId()) {
    flash('error', 'Commande introuvable.');
    header('Location: ' . APP_BASE_URL . '/boutique/my-orders.php');
    exit;
}

$items = $orderModel->getItems($id);

$pageTitle = 'Commande #' . $id;
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">📦 Commande #<?= (int) $id ?></h1>

    <div class="section-block" style="max-width:600px;margin-bottom:2rem">
        <h2 style="margin-bottom:.75rem">Récapitulatif</h2>
        <p>
            <strong>Statut :</strong> <?= e(shopOrderStatusLabel($order['status'])) ?><br>
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
                    <?php $productImgSrc = shopProductImageSrc($item); ?>
                    <tr>
                        <td>
                            <?php if ($productImgSrc !== null): ?>
                                <img src="<?= e($productImgSrc) ?>"
                                     alt="" style="width:36px;height:36px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:.4rem">
                            <?php endif; ?>
                            <?= e($item['product_name']) ?>
                        </td>
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
                    <td colspan="2">Total</td>
                    <td style="text-align:right"><?= formatPrice((int) $order['total_cents']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <a href="<?= APP_BASE_URL ?>/boutique/my-orders.php" class="btn btn--secondary">← Mes commandes</a>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
