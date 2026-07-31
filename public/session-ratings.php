<?php
// public/session-ratings.php – public page to view ratings for a session
require_once __DIR__ . '/init.php';

$sessionId    = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$navContext   = 'sessions';
$sessionModel = new SessionModel();
$session = $sessionModel->findById($sessionId);

if (!$session) {
    http_response_code(404);
    $pageTitle = 'Séance introuvable';
    include ROOT_DIR . '/templates/header.php';
    echo '<div class="container"><p class="flash flash--error">Séance introuvable.</p></div>';
    include ROOT_DIR . '/templates/footer.php';
    exit;
}

$ratingModel = new RatingModel();
$ratings = $ratingModel->getBySession($sessionId);
$average = $ratingModel->getAverageBySession($sessionId);
$count   = count($ratings);

$pageTitle = 'Avis – ' . $session['title'];
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">⭐ Avis – <?= e($session['title']) ?></h1>
    <p style="color:var(--color-muted);margin-bottom:1.5rem">
        📅 <?= e(formatDate($session['session_date'])) ?>
    </p>

    <!-- Summary -->
    <div class="ratings-summary">
        <?php if ($count > 0): ?>
            <span class="ratings-summary__stars">
                <?php
                $avg = round((float) $average, 1);
                $fullStars  = (int) floor($avg);
                $halfStar   = ($avg - $fullStars) >= 0.5;
                for ($i = 1; $i <= 5; $i++):
                    if ($i <= $fullStars):
                        echo '<span class="star star--full">★</span>';
                    elseif ($halfStar && $i === $fullStars + 1):
                        echo '<span class="star star--half">★</span>';
                    else:
                        echo '<span class="star star--empty">★</span>';
                    endif;
                endfor;
                ?>
            </span>
            <span class="ratings-summary__avg"><?= number_format($avg, 1, ',', '') ?> / 5</span>
            <span class="ratings-summary__count">(<?= $count ?> avis)</span>
        <?php else: ?>
            <p style="color:var(--color-muted)">Aucun avis pour le moment.</p>
        <?php endif; ?>
    </div>

    <!-- Individual ratings -->
    <?php if ($ratings): ?>
        <div class="ratings-list">
            <?php foreach ($ratings as $rating): ?>
                <div class="rating-card">
                    <div class="rating-card__header">
                        <span class="rating-card__stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= (int) $rating['stars'] ? 'star--full' : 'star--empty' ?>">★</span>
                            <?php endfor; ?>
                        </span>
                        <span class="rating-card__author">
                            <?php if ($rating['is_anonymous']): ?>
                                <em>Anonyme</em>
                            <?php else: ?>
                                <?= e($rating['first_name'] . ' ' . mb_substr($rating['last_name'], 0, 1) . '.') ?>
                            <?php endif; ?>
                        </span>
                        <span class="rating-card__date">
                            <?= e(date('d/m/Y', strtotime($rating['created_at']))) ?>
                        </span>
                    </div>
                    <?php if (!empty($rating['comment'])): ?>
                        <p class="rating-card__comment"><?= e($rating['comment']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (Auth::isLoggedIn()): ?>
        <?php
        $bookingModel = new BookingModel();
        $hasAttended  = $bookingModel->hasAccessToContent(Auth::currentUserId(), $sessionId);
        if ($hasAttended):
        ?>
            <p class="mt-3">
                <a href="<?= APP_BASE_URL ?>/rate-session.php?session_id=<?= $sessionId ?>" class="btn btn--primary">
                    ⭐ Laisser / modifier mon avis
                </a>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <p class="mt-2"><a href="<?= APP_BASE_URL ?>/ateliers/seance.php?id=<?= $sessionId ?>">← Retour à la séance</a></p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
