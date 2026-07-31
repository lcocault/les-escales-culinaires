<?php
// public/boutique/my-orders.php – user's shop order history
require_once __DIR__ . '/../init.php';
Auth::requireLogin();

$orderModel = new ShopOrderModel();
$orders     = $orderModel->getByUser(Auth::currentUserId());

$pageTitle = 'Mes commandes – Boutique';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">📋 Mes commandes</h1>

    <?php if (empty($orders)): ?>
        <p style="color:var(--color-muted)">Vous n'avez pas encore passé de commande.</p>
        <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--primary" style="margin-top:1rem">🛍️ Voir le catalogue</a>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Livraison</th>
                        <th>Date livraison</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o):
                        // Skip pending (unpaid) orders
                        if ($o['status'] === 'pending') continue;
                    ?>
                        <tr>
                            <td><?= (int) $o['id'] ?></td>
                            <td><?= e(formatDate(substr($o['created_at'], 0, 10))) ?></td>
                            <td><?= e(shopDeliveryLabel($o['delivery_method'])) ?></td>
                            <td><?= e(formatDate($o['delivery_date'])) ?></td>
                            <td><?= formatPrice((int) $o['total_cents']) ?></td>
                            <td><?= e(shopOrderStatusLabel($o['status'])) ?></td>
                            <td>
                                <a href="<?= APP_BASE_URL ?>/boutique/order-detail.php?id=<?= (int) $o['id'] ?>"
                                   class="btn btn--secondary btn--sm">🔍 Détails</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-2">
            <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--secondary">🛍️ Retour au catalogue</a>
        </p>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
