<?php
// public/ateliers/index.php – upcoming cooking sessions listing
require_once __DIR__ . '/../init.php';

$pageTitle      = 'Séances à venir';
$navContext     = 'sessions';
$sessionModel   = new SessionModel();
$sessions       = $sessionModel->getUpcoming(Auth::isLoggedIn() ? Auth::currentUserId() : null);

include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>🍳 Les Escales Culinaires</h1>
        <p>Des ateliers de cuisine pour les petits explorateurs des saveurs !</p>
        <p class="hero__location">📍 36 rue Boieldieu, 31300 Toulouse</p>
        <div class="hero__actions">
            <a href="<?= APP_BASE_URL ?>/ateliers/concept.php" class="btn btn--secondary">✨ Découvrir le concept</a>
            <a href="https://www.instagram.com/les.escales.culinaires" target="_blank" rel="noopener noreferrer" class="btn btn--instagram" aria-label="Nous suivre sur Instagram">
                <?php $instagramIconSize = 18; include ROOT_DIR . '/templates/instagram-icon.php'; ?>
                Instagram
            </a>
        </div>
    </section>

    <p class="text-center mt-2">
        <a href="<?= APP_BASE_URL ?>/all-ratings.php">⭐ Voir tous les avis des participants →</a>
    </p>

    <?php if (empty($sessions)): ?>
        <p class="text-center mt-3" style="color:var(--color-muted)">
            Aucune séance prévue pour le moment. Revenez bientôt !
        </p>
    <?php else: ?>
        <div class="sessions-grid">
            <?php foreach ($sessions as $s): ?>
                <?php
                    $seats = (int) $s['remaining_seats'];
                    if ($seats === 0) {
                        $badgeClass = 'badge--seats-full';
                        $badgeText  = 'Complet';
                    } elseif ($seats <= 3) {
                        $badgeClass = 'badge--seats-low';
                        $badgeText  = $seats . ' place' . ($seats > 1 ? 's' : '') . ' restante' . ($seats > 1 ? 's' : '');
                    } else {
                        $badgeClass = 'badge--seats-ok';
                        $badgeText  = $seats . ' places disponibles';
                    }
                ?>
                <article class="session-card">
                    <div class="session-card__header">
                        <p class="session-card__date"><?= e(formatDate($s['session_date'])) ?></p>
                        <h2 class="session-card__title"><?= e($s['title']) ?><?php if (!empty($s['is_private'])): ?> <span style="font-size:.75em;vertical-align:middle">🔒</span><?php endif; ?></h2>
                    </div>
                    <div class="session-card__body">
                        <p class="session-card__theme">🎨 <?= e($s['theme']) ?></p>
                        <p class="session-card__age">👶 <?= e(ageCategoryLabel($s['age_category'] ?? '6-12')) ?></p>
                        <?php if ($s['summary']): ?>
                            <p class="session-card__summary"><?= e($s['summary']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="session-card__footer">
                        <div>
                            <span class="badge <?= $badgeClass ?>"><?= e($badgeText) ?></span>
                            <p class="session-card__meta mt-1">
                                ⏰ <?= e(substr($s['start_time'], 0, 5)) ?> – <?= e(substr($s['end_time'], 0, 5)) ?>
                                &nbsp;|&nbsp; 💶 <?= e(formatPrice((int) $s['price_cents'])) ?>
                            </p>
                        </div>
                        <a href="<?= APP_BASE_URL ?>/ateliers/seance.php?id=<?= (int) $s['id'] ?>" class="btn btn--primary btn--sm">
                            Détails →
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
