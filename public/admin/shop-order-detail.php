<?php
// public/admin/shop-order-detail.php – view the details of a shop order
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$orderModel = new ShopOrderModel();
$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$order = $id ? $orderModel->findById($id) : null;

if (!$order) {
    flash('error', 'Commande introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/shop-orders.php');
    exit;
}

$items = $orderModel->getItems($id);

$db = Database::getInstance();
$user = $db->prepare('SELECT first_name, last_name, email, phone FROM users WHERE id = :id');
$user->execute([':id' => $order['user_id']]);
$customer = $user->fetch();

$pageTitle = 'Commande #' . $id;
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">📦 Commande #<?= (int) $id ?></h1>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;max-width:800px;margin-bottom:2rem">
        <div class="section-block">
            <h2 style="margin-bottom:.75rem">Client</h2>
            <p>
                <strong><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></strong><br>
                <?= e($customer['email']) ?><br>
                <?php if ($customer['phone']): ?>
                    <?= e($customer['phone']) ?><br>
                <?php endif; ?>
            </p>
        </div>
        <div class="section-block">
            <h2 style="margin-bottom:.75rem">Livraison</h2>
            <p>
                <strong><?= e(shopDeliveryLabel($order['delivery_method'])) ?></strong><br>
                Le <?= e(formatDate($order['delivery_date'])) ?><br>
                <?php if ($order['delivery_method'] === 'home' && !empty($order['delivery_address'])): ?>
                    <em><?= nl2br(e($order['delivery_address'])) ?></em><br>
                    Frais de livraison : <?= formatPrice((int) $order['delivery_fee_cents']) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="section-block" style="max-width:800px;margin-bottom:2rem">
        <h2 style="margin-bottom:.75rem">Articles</h2>
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Prix unitaire</th>
                    <th>Qté</th>
                    <th>Sous-total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $productImgSrc = shopProductImageSrc($item); ?>
                    <tr>
                        <td>
                            <?php if ($productImgSrc !== null): ?>
                                <img src="<?= e($productImgSrc) ?>"
                                     alt="" style="width:36px;height:36px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:.5rem">
                            <?php endif; ?>
                            <?= e($item['product_name']) ?>
                        </td>
                        <td><?= formatPrice((int) $item['unit_price_cents']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td><?= formatPrice((int) $item['unit_price_cents'] * (int) $item['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ((int) $order['delivery_fee_cents'] > 0): ?>
                    <tr>
                        <td colspan="3" style="text-align:right;color:var(--color-muted)">Frais de livraison</td>
                        <td><?= formatPrice((int) $order['delivery_fee_cents']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr style="font-weight:bold">
                    <td colspan="3" style="text-align:right">Total</td>
                    <td><?= formatPrice((int) $order['total_cents']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section-block" style="max-width:800px;margin-bottom:2rem">
        <h2 style="margin-bottom:.75rem">Statut</h2>
        <p><?= e(shopOrderStatusLabel($order['status'])) ?></p>

        <div class="actions" style="margin-top:1rem">
            <?php if ($order['status'] === 'paid'): ?>
                <form method="post" action="<?= APP_BASE_URL ?>/admin/shop-order-update.php"
                      onsubmit="return confirm('Marquer cette commande comme préparée ?')">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="action" value="prepared">
                    <input type="hidden" name="redirect" value="detail">
                    <button type="submit" class="btn btn--primary">🍱 Marquer préparée</button>
                </form>
            <?php elseif ($order['status'] === 'prepared'): ?>
                <form method="post" action="<?= APP_BASE_URL ?>/admin/shop-order-update.php"
                      onsubmit="return confirm('Marquer cette commande comme livrée ?')">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="action" value="delivered">
                    <input type="hidden" name="redirect" value="detail">
                    <button type="submit" class="btn btn--primary">✅ Marquer livrée / remise</button>
                </form>
            <?php endif; ?>
            <?php if (in_array($order['status'], ['paid', 'prepared'], true)): ?>
                <form method="post" action="<?= APP_BASE_URL ?>/admin/shop-order-update.php"
                      onsubmit="return confirm('Annuler cette commande et rembourser le client ?')">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="redirect" value="detail">
                    <button type="submit" class="btn btn--danger">❌ Annuler et rembourser</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <p><a href="<?= APP_BASE_URL ?>/admin/shop-orders.php">← Retour aux commandes</a></p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
