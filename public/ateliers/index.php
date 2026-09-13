<?php
// public/ateliers/index.php – upcoming cooking sessions listing
require_once __DIR__ . '/../init.php';

$pageTitle      = 'Séances à venir';
$navContext     = 'sessions';
$sessionModel   = new SessionModel();
$slotModel      = new GroupSessionSlotModel();
$sessions       = $sessionModel->getUpcomingForCatalog();
$groupSlots     = $slotModel->getUpcoming();

$filterLabels = [
    'all'           => 'Toutes',
    'regular'       => 'Séances régulières',
    'group_private' => 'Séances groupe / privées',
];
$segmentLabels = [
    'regular'       => 'Séance régulière',
    'group_private' => 'Séance groupe / privée',
];

$selectedFilter = isset($_GET['type']) ? (string) $_GET['type'] : 'all';
if (!array_key_exists($selectedFilter, $filterLabels)) {
    $selectedFilter = 'all';
}
$minGroupChildren = (int) GroupBookingModel::MIN_CHILDREN;
$maxGroupChildren = (int) GroupBookingModel::MAX_CHILDREN;
$allItems = buildWorkshopAgendaItems($sessions, $groupSlots);
$visibleItems = filterWorkshopAgendaItems($allItems, $selectedFilter);

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

    <form method="get" class="session-filter" aria-label="Filtrer les types de séances">
        <fieldset class="session-filter__fieldset">
            <legend class="session-filter__legend">Type de séances</legend>
            <?php foreach ($filterLabels as $filterValue => $filterLabel): ?>
                <label class="session-filter__option">
                    <input type="radio" name="type" value="<?= e($filterValue) ?>" <?= $selectedFilter === $filterValue ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="btn btn--sm <?= $selectedFilter === $filterValue ? 'btn--primary' : 'btn--secondary' ?>"><?= e($filterLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <noscript><button type="submit" class="btn btn--secondary btn--sm">Filtrer</button></noscript>
    </form>

    <?php if (empty($visibleItems)): ?>
        <p class="text-center mt-3" style="color:var(--color-muted)">
            <?= empty($allItems)
                ? 'Aucune séance prévue pour le moment. Revenez bientôt !'
                : 'Aucune séance ne correspond au filtre sélectionné.' ?>
        </p>
    <?php else: ?>
        <div class="sessions-grid">
            <?php foreach ($visibleItems as $item): ?>
                <?php if ($item['type'] === 'session'): $s = $item['data']; ?>
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
                            <p class="session-card__type"><span class="badge <?= !empty($s['is_private']) ? 'badge--type-group-private' : 'badge--type-regular' ?>"><?= e(!empty($s['is_private']) ? $segmentLabels['group_private'] : $segmentLabels['regular']) ?></span></p>
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
                <?php elseif ($item['type'] === 'group_slot'): $gs = $item['data']; ?>
                    <?php
                        $groups = (int) $gs['remaining_groups'];
                        if ($groups === 0) {
                            $badgeClass = 'badge--seats-full';
                            $badgeText  = 'Complet';
                        } else {
                            $badgeClass = 'badge--seats-ok';
                            $badgeText  = $groups > 1 ? $groups . ' créneaux disponibles' : $groups . ' créneau disponible';
                        }
                    ?>
                    <article class="session-card">
                        <div class="session-card__header">
                            <p class="session-card__date"><?= e(formatDate($gs['slot_date'])) ?></p>
                            <h2 class="session-card__title"><?= e($gs['title']) ?> <span style="font-size:.75em;vertical-align:middle">🎂</span></h2>
                        </div>
                        <div class="session-card__body">
                            <p class="session-card__theme">🎉 Atelier de groupe / privé</p>
                            <p class="session-card__age">👶 <?= e((string) $minGroupChildren) ?>–<?= e((string) $maxGroupChildren) ?> enfants</p>
                            <p class="session-card__type"><span class="badge badge--type-group-private"><?= e($segmentLabels['group_private']) ?></span></p>
                            <?php if ($gs['description']): ?>
                                <p class="session-card__summary"><?= e($gs['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="session-card__footer">
                            <div>
                                <span class="badge <?= $badgeClass ?>"><?= e($badgeText) ?></span>
                                <p class="session-card__meta mt-1">
                                    ⏰ <?= e(substr($gs['start_time'], 0, 5)) ?> – <?= e(substr($gs['end_time'], 0, 5)) ?>
                                    <?php $hasHomePrice = isset($gs['price_per_child_home_cents']) && $gs['price_per_child_home_cents'] !== null; ?>
                                    <?php $hasEscalesPrice = isset($gs['price_per_child_escales_cents']) && $gs['price_per_child_escales_cents'] !== null; ?>
                                    <?php if ($hasHomePrice): ?>
                                        &nbsp;|&nbsp; 💶 Domicile : <?= e(formatPrice((int) $gs['price_per_child_home_cents'])) ?> / enfant
                                    <?php endif; ?>
                                    <?php if ($hasEscalesPrice): ?>
                                        &nbsp;|&nbsp; 📍 Escales : <?= e(formatPrice((int) $gs['price_per_child_escales_cents'])) ?> / enfant
                                    <?php endif; ?>
                                    <?php if (!$hasHomePrice && !$hasEscalesPrice): ?>
                                        &nbsp;|&nbsp; 💶 Tarif communiqué sur demande
                                    <?php endif; ?>
                                </p>
                            </div>
                            <a href="<?= APP_BASE_URL ?>/group-session-slot.php?id=<?= (int) $gs['id'] ?>" class="btn btn--primary btn--sm">
                                Détails →
                            </a>
                        </div>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
