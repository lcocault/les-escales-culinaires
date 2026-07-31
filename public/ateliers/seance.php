<?php
// public/ateliers/seance.php – session detail (public summary + post-session content for attendees)
require_once __DIR__ . '/../init.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$navContext   = 'sessions';
$sessionModel = new SessionModel();
$session = $sessionModel->findById($id);

if (!$session) {
    http_response_code(404);
    $pageTitle = 'Séance introuvable';
    include ROOT_DIR . '/templates/header.php';
    echo '<div class="container"><p class="flash flash--error">Séance introuvable.</p></div>';
    include ROOT_DIR . '/templates/footer.php';
    exit;
}

// Private session access check
if (!empty($session['is_private']) && !Auth::isAdmin()) {
    $allowed = false;
    if (Auth::isLoggedIn()) {
        $allowed = $sessionModel->isUserAllowed($id, Auth::currentUserId());
    }
    if (!$allowed) {
        $pageTitle = 'Séance privée';
        include ROOT_DIR . '/templates/header.php';
        echo '<div class="container"><div class="flash flash--error" style="margin-top:2rem">
            🔒 Cette séance est privée et n\'est pas accessible au public.
            Si vous pensez avoir été invité(e), vérifiez votre e-mail ou connectez-vous.
            </div></div>';
        include ROOT_DIR . '/templates/footer.php';
        exit;
    }
}

$pageTitle = $session['title'];
$hasAccess = false;
$booking   = null;

if (Auth::isLoggedIn()) {
    $bookingModel = new BookingModel();
    $booking = $bookingModel->findByUserAndSession(Auth::currentUserId(), $id);
    $hasAccess = $bookingModel->hasAccessToContent(Auth::currentUserId(), $id);
}

include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <article class="session-detail">
        <header class="session-detail__header">
            <h1 class="session-detail__title">🍴 <?= e($session['title']) ?></h1>
            <?php if (($session['status'] ?? '') === 'cancelled'): ?>
                <p class="flash flash--error" style="margin-top:.75rem">❌ Cette séance a été annulée. Les participants inscrits ont été remboursés.</p>
            <?php endif; ?>
            <div class="session-detail__meta">
                <span class="session-detail__meta-item">📅 <?= e(formatDate($session['session_date'])) ?></span>
                <span class="session-detail__meta-item">⏰ <?= e(substr($session['start_time'], 0, 5)) ?> – <?= e(substr($session['end_time'], 0, 5)) ?></span>
                <span class="session-detail__meta-item">🎨 <?= e($session['theme']) ?></span>
                <span class="session-detail__meta-item">👶 <?= e(ageCategoryLabel($session['age_category'] ?? '6-12')) ?></span>
                <span class="session-detail__meta-item">💶 <?= e(formatPrice((int) $session['price_cents'])) ?></span>
                <span class="session-detail__meta-item">
                    <?php
                    $seats = (int) $session['remaining_seats'];
                    if ($seats === 0) {
                        echo '<span class="badge badge--seats-full">Complet</span>';
                    } else {
                        echo '<span class="badge badge--seats-ok">' . $seats . ' place' . ($seats > 1 ? 's' : '') . ' disponible' . ($seats > 1 ? 's' : '') . '</span>';
                    }
                    ?>
                </span>
            </div>
        </header>

        <?php if ($session['summary']): ?>
            <section class="section-block">
                <h2>🗒️ Présentation</h2>
                <p><?= nl2br(e($session['summary'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if ($hasAccess): ?>
            <!-- Content for confirmed attendees -->
            <?php if ($session['objectives']): ?>
                <section class="section-block">
                    <h2>🎯 Objectifs pédagogiques</h2>
                    <p><?= nl2br(e($session['objectives'])) ?></p>
                </section>
            <?php endif; ?>

            <?php if ($session['theoretical_content']): ?>
                <section class="section-block">
                    <h2>📖 Contenu théorique</h2>
                    <p><?= nl2br(e($session['theoretical_content'])) ?></p>
                </section>
            <?php endif; ?>

            <?php if ($session['recipe']): ?>
                <section class="section-block">
                    <h2>👨‍🍳 Recette du jour</h2>
                    <p><?= nl2br(e($session['recipe'])) ?></p>
                </section>
            <?php endif; ?>

            <p class="mt-3">
                <a href="<?= APP_BASE_URL ?>/session-content.php?session_id=<?= (int) $session['id'] ?>" class="btn btn--success">
                    📸 Voir les photos &amp; contenus privés →
                </a>
                &nbsp;
                <a href="<?= APP_BASE_URL ?>/rate-session.php?session_id=<?= (int) $session['id'] ?>" class="btn btn--warning">
                    ⭐ Donner mon avis
                </a>
            </p>

        <?php else: ?>
            <!-- Locked content teaser -->
            <div class="section-block locked-content">
                <p>🔒 Le contenu détaillé (objectifs, recette, photos) est réservé aux participants confirmés.</p>
                <?php if (!Auth::isLoggedIn()): ?>
                    <p class="mt-2">
                        <a href="<?= APP_BASE_URL ?>/login.php" class="btn btn--primary">Se connecter</a>
                        &nbsp;ou&nbsp;
                        <a href="<?= APP_BASE_URL ?>/register.php" class="btn btn--secondary">Créer un compte</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Ratings link (always visible) -->
        <?php
        $ratingModel = new RatingModel();
        $ratingsCount = $ratingModel->countBySession($id);
        $ratingsAvg   = $ratingModel->getAverageBySession($id);
        ?>
        <?php if ($ratingsCount > 0): ?>
            <div class="section-block">
                <a href="<?= APP_BASE_URL ?>/session-ratings.php?session_id=<?= $id ?>" class="ratings-teaser-link">
                    <span class="ratings-teaser-link__stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= round((float)$ratingsAvg) ? 'star--full' : 'star--empty' ?>">★</span>
                        <?php endfor; ?>
                    </span>
                    <span class="ratings-teaser-link__avg"><?= number_format((float)$ratingsAvg, 1, ',', '') ?>/5</span>
                    <span class="ratings-teaser-link__count">(<?= $ratingsCount ?> avis)</span>
                    <span class="ratings-teaser-link__cta">→ Voir les avis</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Booking CTA -->
        <div class="mt-3" style="text-align:right">
            <?php
            // Show pack offer when the session belongs to at least one available pack
            $packModel    = new PackModel();
            $sessionPacks = $packModel->getPacksForSessionWithAvailability($id);
            $availablePacks = array_filter($sessionPacks, fn($p) => (int) $p['is_available'] === 1);
            ?>
            <?php if (!empty($availablePacks)): ?>
                <div class="flash flash--info" style="display:inline-block;text-align:left;margin-bottom:.75rem">
                    💡 Cette séance fait partie <?= count($availablePacks) === 1 ? 'd\'un pack' : 'de packs' ?> :
                    <?php foreach ($availablePacks as $pk): ?>
                        <strong><a href="<?= APP_BASE_URL ?>/pack.php?id=<?= (int) $pk['id'] ?>"><?= e($pk['title']) ?></a></strong>
                        (<?= e(formatPrice((int) $pk['price_cents'])) ?>)<?= $pk !== end($availablePacks) ? ', ' : '' ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (($session['status'] ?? '') === 'cancelled'): ?>
                <?php /* session is cancelled – no booking button shown */ ?>
            <?php elseif ($booking && $booking['status'] === 'pending'): ?>
                <p class="flash flash--warning" style="display:inline-block">
                    ⚠️ Vous n'avez pas encore validé le paiement de cette réservation.
                    <a href="<?= APP_BASE_URL ?>/book.php?session_id=<?= (int) $session['id'] ?>" class="btn btn--primary btn--sm" style="margin-left:.5rem">
                        💳 Reprendre le paiement
                    </a>
                </p>
            <?php elseif ($booking && in_array($booking['status'], ['confirmed', 'attended'])): ?>
                <p class="flash flash--info" style="display:inline-block">
                    ✅ Vous avez déjà réservé cette séance.
                </p>
            <?php elseif ((int) $session['remaining_seats'] > 0 && strtotime($session['session_date'] . ' ' . $session['end_time']) >= time()): ?>
                <a href="<?= APP_BASE_URL ?>/book.php?session_id=<?= (int) $session['id'] ?>" class="btn btn--primary">
                    🛒 Réserver cette séance
                </a>
            <?php endif; ?>
        </div>
    </article>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
