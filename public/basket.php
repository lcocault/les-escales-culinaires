<?php
// public/basket.php – user's basket: view items and proceed to checkout
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$basketModel  = new BasketModel();
$sessionModel = new SessionModel();
$bookingModel = new BookingModel();

// Handle item removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_session_id'])) {
    Auth::verifyCsrf();
    $removeSessionId = (int) $_POST['remove_session_id'];
    $basketModel->removeItem(Auth::currentUserId(), $removeSessionId);
    flash('info', 'Séance retirée du panier.');
    header('Location: ' . APP_BASE_URL . '/basket.php');
    exit;
}

$items = $basketModel->getByUser(Auth::currentUserId());

// Enrich items: flag unavailable ones (full, cancelled, already booked, past)
$validItems   = [];
$invalidItems = [];

foreach ($items as $item) {
    $issue = null;

    if (($item['session_status'] ?? '') === 'cancelled') {
        $issue = 'Cette séance a été annulée.';
    } elseif (sessionIsPast($item)) {
        $issue = 'Cette séance est passée.';
    } elseif ((int) $item['remaining_seats'] <= 0) {
        $issue = 'Cette séance est complète.';
    } else {
        $existing = $bookingModel->findByUserAndSession(Auth::currentUserId(), (int) $item['session_id']);
        if ($existing && in_array($existing['status'], ['confirmed', 'attended', 'pending'])) {
            $issue = 'Vous avez déjà réservé cette séance.';
        }
    }

    if ($issue !== null) {
        $invalidItems[] = array_merge($item, ['_issue' => $issue]);
    } else {
        $validItems[] = $item;
    }
}

$totalCents = array_sum(array_column($validItems, 'price_cents'));

$pageTitle = 'Mon panier';
$navContext = 'sessions';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🛒 Mon panier</h1>

    <?php if (empty($items)): ?>
        <div class="basket-empty">
            <p>Votre panier est vide.</p>
            <a href="<?= APP_BASE_URL ?>/" class="btn btn--primary mt-2">Voir les séances disponibles</a>
        </div>
    <?php else: ?>

        <?php if (!empty($invalidItems)): ?>
            <div class="flash flash--error mb-2">
                <strong>⚠️ Certaines séances de votre panier ne sont plus disponibles :</strong>
                <ul style="margin-top:.5rem;padding-left:1.25rem">
                    <?php foreach ($invalidItems as $inv): ?>
                        <li><?= e($inv['title']) ?> – <?= e($inv['_issue']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-1">Veuillez les retirer avant de procéder au paiement.</p>
            </div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Séance</th>
                        <th>Date</th>
                        <th>Enfant</th>
                        <th>Prix</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $isInvalid = false;
                        foreach ($invalidItems as $inv) {
                            if ((int) $inv['session_id'] === (int) $item['session_id']) {
                                $isInvalid = true;
                                break;
                            }
                        }
                        ?>
                        <tr class="<?= $isInvalid ? 'basket-row--invalid' : '' ?>">
                            <td>
                                <a href="<?= APP_BASE_URL ?>/ateliers/seance.php?id=<?= (int) $item['session_id'] ?>">
                                    <?= e($item['title']) ?>
                                </a>
                                <?php if ($isInvalid): ?>
                                    <span class="badge badge--seats-full" style="margin-left:.4rem">Indisponible</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(formatDate($item['session_date'])) ?>
                                <br><small><?= e(substr($item['start_time'], 0, 5)) ?> – <?= e(substr($item['end_time'], 0, 5)) ?></small>
                            </td>
                            <td>
                                <?php if ($item['child_first_name']): ?>
                                    <?= e($item['child_first_name'] . ' ' . $item['child_last_name']) ?>
                                    <?php if ($item['child_age']): ?>
                                        <small>(<?= (int) $item['child_age'] ?> ans)</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    –
                                <?php endif; ?>
                            </td>
                            <td><?= e(formatPrice((int) $item['price_cents'])) ?></td>
                            <td>
                                <form method="post" action=""
                                      onsubmit="return confirm('Retirer cette séance du panier ?')">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                    <input type="hidden" name="remove_session_id" value="<?= (int) $item['session_id'] ?>">
                                    <button type="submit" class="btn btn--danger btn--sm">🗑 Retirer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($validItems)): ?>
            <div class="basket-summary">
                <div class="basket-summary__total">
                    Total : <strong><?= e(formatPrice($totalCents)) ?></strong>
                    (<?= count($validItems) ?> séance<?= count($validItems) > 1 ? 's' : '' ?>)
                </div>
                <form method="post" action="<?= APP_BASE_URL ?>/basket-checkout.php">
                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                    <button type="submit" class="btn btn--primary btn--lg">
                        💳 Payer le panier (<?= e(formatPrice($totalCents)) ?>)
                    </button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
