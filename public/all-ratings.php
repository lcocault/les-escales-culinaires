<?php
// public/all-ratings.php – public page: all ratings left by attendees
require_once __DIR__ . '/init.php';

$ratingModel = new RatingModel();
$ratings     = $ratingModel->getAll();
$totalCount  = count($ratings);
$overallAvg  = $ratingModel->getOverallAverage();

$pageTitle = 'Tous les avis';
$navContext = 'sessions';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">⭐ Tous les avis</h1>
    <p style="color:var(--color-muted);margin-bottom:1.5rem">
        Ce que les participants disent de nos ateliers.
    </p>

    <!-- Overall summary -->
    <?php if ($totalCount > 0): ?>
        <div class="ratings-summary" style="margin-bottom:2rem">
            <span class="ratings-summary__stars">
                <?php
                $avg       = round((float) $overallAvg, 1);
                $fullStars = (int) floor($avg);
                $halfStar  = ($avg - $fullStars) >= 0.5;
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
            <span class="ratings-summary__count">(<?= $totalCount ?> avis au total)</span>
        </div>
    <?php else: ?>
        <p style="color:var(--color-muted)">Aucun avis pour le moment. Revenez bientôt !</p>
    <?php endif; ?>

    <!-- All ratings -->
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
                    <p class="rating-card__session-link">
                        <a href="<?= APP_BASE_URL ?>/session-ratings.php?session_id=<?= (int) $rating['session_id'] ?>">
                            📅 <?= e($rating['session_title']) ?>
                            – <?= e(date('d/m/Y', strtotime($rating['session_date']))) ?>
                        </a>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="mt-3"><a href="<?= APP_BASE_URL ?>/">← Retour aux séances</a></p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
