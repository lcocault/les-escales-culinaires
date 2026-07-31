<?php
// public/admin/shop-market-days.php – manage market candidate delivery dates
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$orderModel = new ShopOrderModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $date = trim($_POST['delivery_date'] ?? '');
    if (!ShopOrderModel::isMarketDeliveryDate($date)) {
        flash('error', 'Date de marché invalide.');
    } else {
        $counts = $orderModel->getOrderCountsByDeliveryDates([$date]);
        $ordersCount = (int) ($counts[$date] ?? 0);
        if ($ordersCount > 0) {
            flash('error', 'Impossible d’annuler cette date : une commande est déjà enregistrée.');
        } else {
            $orderModel->cancelMarketDate($date);
            flash('success', 'Date de marché annulée.');
        }
    }

    header('Location: ' . APP_BASE_URL . '/admin/shop-market-days.php');
    exit;
}

$candidates = ShopOrderModel::candidateMarketDates(16);
$candidateDates = array_column($candidates, 'date');
$cancelledSet = array_fill_keys($orderModel->getCancelledMarketDates(), true);
$orderCounts = $orderModel->getOrderCountsByDeliveryDates($candidateDates);

$pageTitle = 'Boutique – Dates marché';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🧺 Boutique – Dates de retrait marché</h1>
    <p style="color:var(--color-muted);max-width:780px">
        Les dates candidates sont calculées automatiquement avec un délai de préparation de
        <?= ShopOrderModel::MARKET_PREPARATION_HOURS ?>h. Vous pouvez annuler une date seulement si
        aucune commande n’est enregistrée pour cette date.
    </p>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date candidate</th>
                    <th>Type</th>
                    <th>Commandes</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $candidate): ?>
                    <?php
                        $date = $candidate['date'];
                        $isCancelled = isset($cancelledSet[$date]);
                        $ordersCount = (int) ($orderCounts[$date] ?? 0);
                    ?>
                    <tr>
                        <td><?= e(formatDate($date)) ?></td>
                        <td><?= $candidate['method'] === 'market_wednesday' ? 'Mercredi' : 'Vendredi' ?></td>
                        <td><?= $ordersCount ?></td>
                        <td>
                            <?php if ($isCancelled): ?>
                                <span style="color:var(--color-danger)">Annulée</span>
                            <?php else: ?>
                                <span style="color:var(--color-success)">Disponible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isCancelled && $ordersCount === 0): ?>
                                <form method="post" action=""
                                      onsubmit="return confirm('Confirmer l’annulation de cette date de marché ?')">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                    <input type="hidden" name="delivery_date" value="<?= e($date) ?>">
                                    <button type="submit" class="btn btn--danger btn--sm">❌ Annuler</button>
                                </form>
                            <?php elseif ($isCancelled): ?>
                                <small style="color:var(--color-muted)">Déjà annulée</small>
                            <?php else: ?>
                                <small style="color:var(--color-muted)">Commande existante</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="mt-3"><a href="<?= APP_BASE_URL ?>/admin/shop-orders.php">← Retour aux commandes boutique</a></p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
