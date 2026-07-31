<?php
// public/pack.php – pack detail and booking entry point
require_once __DIR__ . '/init.php';

$id        = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$navContext = 'sessions';
$packModel  = new PackModel();
$pack = $packModel->findById($id);

if (!$pack) {
    http_response_code(404);
    $pageTitle = 'Pack introuvable';
    include ROOT_DIR . '/templates/header.php';
    echo '<div class="container"><p class="flash flash--error">Pack introuvable.</p></div>';
    include ROOT_DIR . '/templates/footer.php';
    exit;
}

$sessions    = $packModel->getSessionsForPack($id);
$isAvailable = $packModel->isAvailable($id);

// Check whether the user already has a booking for every session in this pack
$alreadyBooked = false;
if (Auth::isLoggedIn() && !empty($sessions)) {
    $bookingModel = new BookingModel();
    $bookedCount  = 0;
    foreach ($sessions as $s) {
        $b = $bookingModel->findByUserAndSession(Auth::currentUserId(), (int) $s['id']);
        if ($b && in_array($b['status'], ['confirmed', 'attended', 'pending'], true)) {
            $bookedCount++;
        }
    }
    $alreadyBooked = ($bookedCount === count($sessions));
}

$pageTitle = $pack['title'];
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <article class="session-detail">
        <header class="session-detail__header">
            <h1 class="session-detail__title">📦 <?= e($pack['title']) ?></h1>
            <div class="session-detail__meta">
                <span class="session-detail__meta-item">💶 <?= e(formatPrice((int) $pack['price_cents'])) ?> <span style="color:var(--color-muted);font-size:.85rem">(pack)</span></span>
                <span class="session-detail__meta-item">📅 <?= count($sessions) ?> séance(s)</span>
                <?php if ($isAvailable): ?>
                    <span class="badge badge--seats-ok">Disponible</span>
                <?php else: ?>
                    <span class="badge badge--seats-full">Indisponible</span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($pack['description']): ?>
            <section class="section-block">
                <h2>🗒️ Présentation</h2>
                <p><?= nl2br(e($pack['description'])) ?></p>
            </section>
        <?php endif; ?>

        <section class="section-block">
            <h2>📅 Séances incluses</h2>
            <?php if (empty($sessions)): ?>
                <p style="color:var(--color-muted)">Aucune séance associée à ce pack.</p>
            <?php else: ?>
                <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.75rem">
                    <?php foreach ($sessions as $s): ?>
                        <li style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                            <span>
                                <a href="<?= APP_BASE_URL ?>/ateliers/seance.php?id=<?= (int) $s['id'] ?>" style="font-weight:600">
                                    <?= e($s['title']) ?>
                                </a>
                                <span style="color:var(--color-muted);font-size:.9rem">
                                    — <?= e(formatDate($s['session_date'])) ?>
                                    <?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?>
                                </span>
                            </span>
                            <?php if ((int) $s['remaining_seats'] === 0 || ($s['status'] ?? '') === 'cancelled'): ?>
                                <span class="badge badge--seats-full">
                                    <?= ($s['status'] ?? '') === 'cancelled' ? 'Annulée' : 'Complet' ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge--seats-ok"><?= (int) $s['remaining_seats'] ?> place(s)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Booking CTA -->
        <div class="mt-3" style="text-align:right">
            <?php if ($alreadyBooked): ?>
                <p class="flash flash--info" style="display:inline-block">
                    ✅ Vous avez déjà réservé toutes les séances de ce pack.
                </p>
            <?php elseif (!$isAvailable): ?>
                <p class="flash flash--warning" style="display:inline-block">
                    ⚠️ Ce pack n'est pas disponible car une ou plusieurs séances sont complètes ou annulées.
                </p>
            <?php elseif (!Auth::isLoggedIn()): ?>
                <a href="<?= APP_BASE_URL ?>/login.php" class="btn btn--primary">
                    Se connecter pour réserver
                </a>
            <?php else: ?>
                <a href="<?= APP_BASE_URL ?>/pack-book.php?pack_id=<?= (int) $pack['id'] ?>" class="btn btn--primary">
                    🛒 Réserver ce pack (<?= e(formatPrice((int) $pack['price_cents'])) ?>)
                </a>
            <?php endif; ?>
        </div>
    </article>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
