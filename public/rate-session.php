<?php
// public/rate-session.php – submit a rating for an attended session
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$sessionModel = new SessionModel();
$session = $sessionModel->findById($sessionId);

if (!$session) {
    flash('error', 'Séance introuvable.');
    header('Location: ' . APP_BASE_URL . '/');
    exit;
}

$bookingModel = new BookingModel();
if (!$bookingModel->hasAccessToContent(Auth::currentUserId(), $sessionId)) {
    flash('error', 'Vous devez avoir participé à cette séance pour laisser un avis.');
    header('Location: ' . APP_BASE_URL . '/ateliers/seance.php?id=' . $sessionId);
    exit;
}

// Find the booking
$booking = $bookingModel->findByUserAndSession(Auth::currentUserId(), $sessionId);

$ratingModel = new RatingModel();
$existingRating = $ratingModel->findByUserAndSession(Auth::currentUserId(), $sessionId);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $stars       = isset($_POST['stars']) ? (int) $_POST['stars'] : -1;
    $comment     = trim($_POST['comment'] ?? '');
    $isAnonymous = !empty($_POST['is_anonymous']);

    if ($stars < 0 || $stars > 5) {
        $errors[] = 'Veuillez choisir une note entre 0 et 5 étoiles.';
    }
    if (mb_strlen($comment) > 200) {
        $errors[] = 'Le commentaire ne peut pas dépasser 200 caractères.';
    }

    if (empty($errors)) {
        if ($existingRating) {
            // Update existing rating
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'UPDATE ratings SET stars = :stars, comment = :comment, is_anonymous = :anon
                 WHERE user_id = :uid AND session_id = :sid'
            );
            $stmt->execute([
                ':stars'   => $stars,
                ':comment' => $comment !== '' ? $comment : null,
                ':anon'    => $isAnonymous ? 'TRUE' : 'FALSE',
                ':uid'     => Auth::currentUserId(),
                ':sid'     => $sessionId,
            ]);
        } else {
            $ratingModel->create(
                (int) $booking['id'],
                Auth::currentUserId(),
                $sessionId,
                $stars,
                $comment,
                $isAnonymous
            );
        }

        flash('success', 'Merci pour votre avis !');
        header('Location: ' . APP_BASE_URL . '/session-ratings.php?session_id=' . $sessionId);
        exit;
    }
}

$pageTitle = 'Donner mon avis – ' . $session['title'];
$navContext = 'sessions';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">⭐ Donner mon avis</h1>

    <div class="session-detail" style="max-width:600px;margin:0 auto">
        <p style="color:var(--color-muted);margin-bottom:1.5rem">
            Séance : <strong><?= e($session['title']) ?></strong> –
            <?= e(formatDate($session['session_date'])) ?>
        </p>

        <?php if ($existingRating): ?>
            <div class="flash flash--info">✏️ Vous avez déjà laissé un avis. Vous pouvez le modifier ci-dessous.</div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="flash flash--error">
                <?php foreach ($errors as $err): ?>
                    <p><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= APP_BASE_URL ?>/rate-session.php?session_id=<?= $sessionId ?>">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

            <!-- Star rating -->
            <div class="form-group">
                <label>Note (nombre d'étoiles)</label>
                <div class="star-rating" role="group" aria-label="Note de 0 à 5 étoiles">
                    <?php
                    $currentStars = $existingRating ? (int) $existingRating['stars'] : -1;
                    for ($i = 5; $i >= 1; $i--):
                        $checked = ($currentStars === $i) ? 'checked' : '';
                    ?>
                        <input type="radio" id="star<?= $i ?>" name="stars" value="<?= $i ?>"
                               <?= $checked ?> required>
                        <label for="star<?= $i ?>" title="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">★</label>
                    <?php endfor; ?>
                    <input type="radio" id="star0" name="stars" value="0"
                           <?= ($currentStars === 0) ? 'checked' : '' ?>>
                    <label for="star0" class="star-zero" title="0 étoile">☆</label>
                </div>
            </div>

            <!-- Comment -->
            <div class="form-group">
                <label for="comment">Commentaire <span style="font-weight:400;color:var(--color-muted)">(facultatif, 200 caractères max.)</span></label>
                <textarea id="comment" name="comment" rows="4"
                          maxlength="200"
                          placeholder="Partagez votre expérience…"><?= e($existingRating['comment'] ?? '') ?></textarea>
                <p class="form-hint"><span id="comment-count">0</span> / 200 caractères</p>
            </div>

            <!-- Anonymous -->
            <div class="form-group form-group--checkbox">
                <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                       <?= (!empty($existingRating['is_anonymous'])) ? 'checked' : '' ?>>
                <label for="is_anonymous">Publier cet avis de manière anonyme</label>
            </div>

            <button type="submit" class="btn btn--primary">
                <?= $existingRating ? '✏️ Modifier mon avis' : '⭐ Publier mon avis' ?>
            </button>
        </form>

        <p class="mt-2">
            <a href="<?= APP_BASE_URL ?>/session-ratings.php?session_id=<?= $sessionId ?>">
                Voir tous les avis de cette séance →
            </a>
        </p>
    </div>
</div>

<script>
(function () {
    var ta = document.getElementById('comment');
    var counter = document.getElementById('comment-count');
    function update() { counter.textContent = ta.value.length; }
    ta.addEventListener('input', update);
    update();
})();
</script>

<?php include ROOT_DIR . '/templates/footer.php'; ?>
