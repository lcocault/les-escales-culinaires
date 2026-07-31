<?php
// public/group-session-slot.php – public detail page for an admin-defined group session slot
require_once __DIR__ . '/init.php';

$id        = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slotModel = new GroupSessionSlotModel();
$slot      = $slotModel->findById($id);

if (!$slot) {
    http_response_code(404);
    $pageTitle = 'Créneau introuvable';
    include ROOT_DIR . '/templates/header.php';
    echo '<div class="container"><p class="flash flash--error">Créneau introuvable.</p></div>';
    include ROOT_DIR . '/templates/footer.php';
    exit;
}

$pageTitle  = $slot['title'];
$isCancelled = $slot['status'] === 'cancelled';
$isFull      = (int) $slot['remaining_groups'] <= 0;
$isPast      = strtotime($slot['slot_date'] . ' ' . $slot['end_time']) < time();

include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <article class="session-detail">
        <header class="session-detail__header">
            <h1 class="session-detail__title">🎂 <?= e($slot['title']) ?></h1>

            <?php if ($isCancelled): ?>
                <p class="flash flash--error" style="margin-top:.75rem">❌ Ce créneau a été annulé.</p>
            <?php endif; ?>

            <div class="session-detail__meta">
                <span class="session-detail__meta-item">📅 <?= e(formatDate($slot['slot_date'])) ?></span>
                <span class="session-detail__meta-item">⏰ <?= e(substr($slot['start_time'], 0, 5)) ?> – <?= e(substr($slot['end_time'], 0, 5)) ?></span>
                <span class="session-detail__meta-item">🏠 <?= e(formatPrice((int) $slot['price_per_child_home_cents'])) ?>/enfant (domicile)</span>
                <span class="session-detail__meta-item">📍 <?= e(formatPrice((int) $slot['price_per_child_escales_cents'])) ?>/enfant (Escales)</span>
                <span class="session-detail__meta-item">
                    <?php if ($isCancelled): ?>
                        <span class="badge badge--seats-full">Annulé</span>
                    <?php elseif ($isFull || $isPast): ?>
                        <span class="badge badge--seats-full">Complet</span>
                    <?php else: ?>
                        <?php $groups = (int) $slot['remaining_groups']; ?>
                        <span class="badge badge--seats-ok"><?= $groups > 1 ? $groups . ' créneaux disponibles' : $groups . ' créneau disponible' ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </header>

        <section class="section-block">
            <h2>🗒️ Présentation</h2>
            <?php if ($slot['description']): ?>
                <p><?= nl2br(e($slot['description'])) ?></p>
            <?php else: ?>
                <p>Offrez à votre enfant et à ses amis un moment inoubliable aux fourneaux !
                   Un atelier culinaire privé de 2 heures, animé par nos équipes,
                   pour <strong><?= GroupBookingModel::MIN_CHILDREN ?> à <?= GroupBookingModel::MAX_CHILDREN ?> enfants</strong>.</p>
            <?php endif; ?>

            <ul style="margin:1rem 0;padding-left:1.4rem;line-height:1.8">
                <li>👶 <strong><?= GroupBookingModel::MIN_CHILDREN ?> à <?= GroupBookingModel::MAX_CHILDREN ?> enfants</strong></li>
                <li>🏠 <strong>À domicile : <?= e(formatPrice((int) $slot['price_per_child_home_cents'])) ?> par enfant</strong>
                    (soit <?= e(formatPrice(GroupBookingModel::MIN_CHILDREN * (int) $slot['price_per_child_home_cents'])) ?>
                    à <?= e(formatPrice(GroupBookingModel::MAX_CHILDREN * (int) $slot['price_per_child_home_cents'])) ?> au total)</li>
                <li>📍 <strong>Aux Escales Culinaires : <?= e(formatPrice((int) $slot['price_per_child_escales_cents'])) ?> par enfant</strong>
                    (soit <?= e(formatPrice(GroupBookingModel::MIN_CHILDREN * (int) $slot['price_per_child_escales_cents'])) ?>
                    à <?= e(formatPrice(GroupBookingModel::MAX_CHILDREN * (int) $slot['price_per_child_escales_cents'])) ?> au total)</li>
                <li>🥜 Le menu est adapté aux allergies des enfants</li>
            </ul>

            <p style="color:var(--color-muted);font-size:.9rem">
                Après réception de votre demande, nous vous contacterons pour confirmer la réservation et finaliser le menu.
            </p>
        </section>

        <!-- Booking CTA -->
        <div class="mt-3" style="text-align:right">
            <?php if ($isCancelled || $isPast): ?>
                <?php /* no booking button */ ?>
            <?php elseif ($isFull): ?>
                <p class="flash flash--warning" style="display:inline-block">Ce créneau est complet.</p>
            <?php elseif (!Auth::isLoggedIn()): ?>
                <p class="flash flash--info" style="display:inline-block;text-align:left">
                    Connectez-vous pour réserver ce créneau.
                </p>
                <p class="mt-2">
                    <a href="<?= APP_BASE_URL ?>/login.php" class="btn btn--primary">Se connecter</a>
                    &nbsp;ou&nbsp;
                    <a href="<?= APP_BASE_URL ?>/register.php" class="btn btn--secondary">Créer un compte</a>
                </p>
            <?php else: ?>
                <a href="<?= APP_BASE_URL ?>/group-booking.php?slot_id=<?= (int) $slot['id'] ?>" class="btn btn--primary">
                    🛒 Réserver ce créneau
                </a>
            <?php endif; ?>
        </div>
    </article>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
