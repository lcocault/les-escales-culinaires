<?php
// public/admin/shop-orders.php – manage shop orders
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$orderModel = new ShopOrderModel();
$orders = $orderModel->getAll();

$pageTitle = 'Boutique – Commandes';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">📦 Commandes boutique</h1>
    <p class="mb-2">
        <a href="<?= APP_BASE_URL ?>/admin/shop-market-days.php" class="btn btn--secondary btn--sm">🧺 Gérer les dates marché candidates</a>
    </p>

    <?php if (empty($orders)): ?>
        <p style="color:var(--color-muted)">Aucune commande pour l'instant.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Date commande</th>
                        <th>Livraison</th>
                        <th>Date livraison</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?= (int) $o['id'] ?></td>
                            <td>
                                <?= e($o['first_name'] . ' ' . $o['last_name']) ?><br>
                                <small style="color:var(--color-muted)"><?= e($o['email']) ?></small>
                            </td>
                            <td><?= e(formatDate(substr($o['created_at'], 0, 10))) ?></td>
                            <td><?= e(shopDeliveryLabel($o['delivery_method'])) ?></td>
                            <td><?= e(formatDate($o['delivery_date'])) ?></td>
                            <td><?= formatPrice((int) $o['total_cents']) ?></td>
                            <td><?= e(shopOrderStatusLabel($o['status'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="<?= APP_BASE_URL ?>/admin/shop-order-detail.php?id=<?= (int) $o['id'] ?>"
                                       class="btn btn--secondary btn--icon" title="Détails" aria-label="Détails">🔍</a>
                                    <?php if ($o['status'] === 'paid'): ?>
                                        <form method="post" action="<?= APP_BASE_URL ?>/admin/shop-order-update.php"
                                              onsubmit="return confirm('Marquer cette commande comme préparée ?')">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <input type="hidden" name="action" value="prepared">
                                            <button type="submit" class="btn btn--primary btn--sm" title="Marquer préparée">🍱 Préparée</button>
                                        </form>
                                    <?php elseif ($o['status'] === 'prepared'): ?>
                                        <form method="post" action="<?= APP_BASE_URL ?>/admin/shop-order-update.php"
                                              onsubmit="return confirm('Marquer cette commande comme livrée ?')">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <input type="hidden" name="action" value="delivered">
                                            <button type="submit" class="btn btn--primary btn--sm" title="Marquer livrée">✅ Livrée</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($o['status'], ['paid', 'prepared'], true)): ?>
                                        <form method="post" action="<?= APP_BASE_URL ?>/admin/shop-order-update.php"
                                              onsubmit="return confirm('Annuler cette commande et rembourser le client ?')">
                                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn--danger btn--sm" title="Annuler">❌ Annuler</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="mt-3"><a href="<?= APP_BASE_URL ?>/admin/">← Retour au tableau de bord</a></p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
