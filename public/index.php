<?php
// public/index.php – portal homepage: learning sessions & shop
require_once __DIR__ . '/init.php';

$pageTitle       = 'Accueil';
$navContext      = 'home';
$sessionModel    = new SessionModel();
$sessions        = $sessionModel->getUpcoming(Auth::isLoggedIn() ? Auth::currentUserId() : null);
$slotModel       = new GroupSessionSlotModel();
$groupSlots      = $slotModel->getUpcoming();
$messageModel    = new GeneralMessageModel();
$latestMessage   = $messageModel->getLatest();
$hasMoreMessages = $messageModel->countAll() > 1;

$allItems = [];
foreach ($sessions as $s) {
    $allItems[] = ['type' => 'session', 'date' => $s['session_date'], 'time' => $s['start_time'], 'data' => $s];
}
foreach ($groupSlots as $gs) {
    $allItems[] = ['type' => 'group_slot', 'date' => $gs['slot_date'], 'time' => $gs['start_time'], 'data' => $gs];
}
usort($allItems, fn($a, $b) => strcmp($a['date'] . $a['time'], $b['date'] . $b['time']));

include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>🍳 Les Escales Culinaires</h1>
        <p>Ateliers de cuisine &amp; boutique gourmande à Toulouse</p>
        <p class="hero__location">📍 36 rue Boieldieu, 31300 Toulouse</p>
    </section>

    <?php if ($latestMessage !== null): ?>
        <section class="news-thread" aria-label="Actualités">
            <div class="news-item news-item--<?= e($latestMessage['type']) ?>">
                <span class="news-item__icon" aria-hidden="true"><?= [
                    'info'    => '💬',
                    'warning' => '⚠️',
                    'danger'  => '🚨',
                    'success' => '✅',
                ][$latestMessage['type']] ?? '📢' ?></span>
                <div class="news-item__body">
                    <p class="news-item__date"><?= e(date('d/m/Y', strtotime($latestMessage['created_at']))) ?></p>
                    <p class="news-item__text"><?= e($latestMessage['body']) ?></p>
                </div>
            </div>
            <?php if ($hasMoreMessages): ?>
                <p class="news-thread__more">
                    <a href="<?= APP_BASE_URL ?>/messages.php">📋 Voir tous les messages →</a>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="portal-grid">
        <a href="<?= APP_BASE_URL ?>/ateliers/" class="portal-card portal-card--sessions">
            <span class="portal-card__icon">🍳</span>
            <h2 class="portal-card__title">Ateliers de cuisine</h2>
            <p class="portal-card__desc">Des cours de cuisine ludiques et pédagogiques pour les enfants. Réservez votre séance en ligne !</p>
            <span class="btn btn--primary portal-card__cta">Voir les séances →</span>
        </a>

        <a href="<?= APP_BASE_URL ?>/boutique/" class="portal-card portal-card--shop">
            <span class="portal-card__icon">🛍️</span>
            <h2 class="portal-card__title">Boutique</h2>
            <p class="portal-card__desc">Plats préparés maison à commander en ligne. Retrait au marché, en boutique ou livraison à domicile !</p>
            <span class="btn btn--primary portal-card__cta">Voir le catalogue →</span>
        </a>
    </div>

    <section class="section-block">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
            <div>
                <h2 style="margin-bottom:.35rem">📅 Prochains ateliers</h2>
                <p style="margin:0;color:var(--color-muted)">Retrouvez ici un aperçu des séances enfants et des créneaux anniversaires à venir.</p>
            </div>
            <a href="<?= APP_BASE_URL ?>/ateliers/" class="btn btn--secondary">Voir tout l'agenda →</a>
        </div>

        <?php if (empty($allItems)): ?>
            <p class="text-center mt-3" style="color:var(--color-muted)">
                Aucune séance prévue pour le moment. Revenez bientôt !
            </p>
        <?php else: ?>
            <div class="sessions-grid" style="margin-top:1.5rem">
                <?php foreach ($allItems as $item): ?>
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
                            $badgeText  = $groups > 1
                                ? $groups . ' créneaux disponibles'
                                : $groups . ' créneau disponible';
                        }
                        $priceHomeCents    = (int) $gs['price_per_child_home_cents'];
                        $priceEscalesCents = (int) $gs['price_per_child_escales_cents'];
                    ?>
                    <article class="session-card">
                        <div class="session-card__header">
                            <p class="session-card__date"><?= e(formatDate($gs['slot_date'])) ?></p>
                            <h2 class="session-card__title"><?= e($gs['title']) ?> <span style="font-size:.75em;vertical-align:middle">🎂</span></h2>
                        </div>
                        <div class="session-card__body">
                            <p class="session-card__theme">🎉 Atelier de groupe – anniversaire</p>
                            <p class="session-card__age">👶 <?= GroupBookingModel::MIN_CHILDREN ?>–<?= GroupBookingModel::MAX_CHILDREN ?> enfants</p>
                            <?php if ($gs['description']): ?>
                                <p class="session-card__summary"><?= e($gs['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="session-card__footer">
                            <div>
                                <span class="badge <?= $badgeClass ?>"><?= e($badgeText) ?></span>
                                <p class="session-card__meta mt-1">
                                    ⏰ <?= e(substr($gs['start_time'], 0, 5)) ?> – <?= e(substr($gs['end_time'], 0, 5)) ?>
                                    &nbsp;|&nbsp; 💶 <?= e(formatPrice($priceHomeCents)) ?> – <?= e(formatPrice($priceEscalesCents)) ?>/enfant
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
    </section>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
