<?php
// public/pack-book.php – collect child info and redirect to payment for a full pack
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$packId    = isset($_GET['pack_id']) ? (int) $_GET['pack_id'] : 0;
$packModel = new PackModel();
$pack      = $packModel->findById($packId);

if (!$pack) {
    flash('error', 'Pack introuvable.');
    header('Location: ' . APP_BASE_URL . '/');
    exit;
}

$sessions = $packModel->getSessionsForPack($packId);

if (empty($sessions)) {
    flash('error', 'Ce pack ne contient aucune séance.');
    header('Location: ' . APP_BASE_URL . '/pack.php?id=' . $packId);
    exit;
}

if (!$packModel->isAvailable($packId)) {
    flash('error', 'Ce pack n\'est plus disponible (une ou plusieurs séances sont complètes ou annulées).');
    header('Location: ' . APP_BASE_URL . '/pack.php?id=' . $packId);
    exit;
}

$bookingModel = new BookingModel();

// Prevent double-booking: check that the user has no confirmed/attended booking for any session
foreach ($sessions as $s) {
    $existing = $bookingModel->findByUserAndSession(Auth::currentUserId(), (int) $s['id']);
    if ($existing && in_array($existing['status'], ['confirmed', 'attended'], true)) {
        flash('info', 'Vous avez déjà réservé la séance « ' . $s['title'] . ' » qui fait partie de ce pack.');
        header('Location: ' . APP_BASE_URL . '/my-sessions.php');
        exit;
    }
}

$userModel = new UserModel();
$user      = $userModel->findById(Auth::currentUserId());
$errors    = [];

$childFirstName = '';
$childLastName  = $user['last_name'] ?? '';
$childAge       = '';
$childAllergies = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $childFirstName = trim($_POST['child_first_name'] ?? '');
    $childLastName  = trim($_POST['child_last_name']  ?? '');
    $childAge       = trim($_POST['child_age']        ?? '');
    $childAllergies = trim($_POST['child_allergies']  ?? '');

    if ($childFirstName === '') {
        $errors[] = 'Le prénom de l\'enfant est obligatoire.';
    }
    if ($childLastName === '') {
        $errors[] = 'Le nom de l\'enfant est obligatoire.';
    }
    if ($childAge === '' || !ctype_digit($childAge) || (int) $childAge < 1 || (int) $childAge > 17) {
        $errors[] = 'L\'âge de l\'enfant doit être un nombre entier entre 1 et 17.';
    }

    if (empty($errors)) {
        // Re-check availability at submission time
        if (!$packModel->isAvailable($packId)) {
            flash('error', 'Ce pack n\'est plus disponible. Veuillez choisir un autre pack ou des séances individuelles.');
            header('Location: ' . APP_BASE_URL . '/pack.php?id=' . $packId);
            exit;
        }

        // Create one pending booking per session
        $bookingIds = [];
        foreach ($sessions as $s) {
            $sessionId = (int) $s['id'];
            // Re-use existing pending booking if present
            $existing = $bookingModel->findByUserAndSession(Auth::currentUserId(), $sessionId);
            if ($existing && $existing['status'] === 'pending') {
                $bookingIds[] = (int) $existing['id'];
            } else {
                $bookingIds[] = $bookingModel->create(
                    Auth::currentUserId(),
                    $sessionId,
                    false,
                    $childFirstName,
                    $childLastName,
                    (int) $childAge,
                    $childAllergies
                );
            }
        }

        // Store booking IDs in session for the payment success/cancel handlers
        Auth::start();
        $_SESSION['pack_checkout_booking_ids'] = $bookingIds;
        $_SESSION['pack_checkout_pack_id']     = $packId;

        // Build line items for the payment provider (one line per session in the pack)
        // The total is the pack price, not the sum of individual session prices.
        $lineItems = [
            ['name' => $pack['title'] . ' (pack)', 'amount_cents' => (int) $pack['price_cents']],
        ];

        try {
            $checkout = PaymentService::createBasketCheckoutUrl(
                $lineItems,
                (int) $pack['price_cents'],
                'eur',
                'pack'
            );
        } catch (RuntimeException $e) {
            // Clean up the pending bookings we just created
            foreach ($bookingIds as $bid) {
                $bookingModel->deleteById($bid);
            }
            unset($_SESSION['pack_checkout_booking_ids'], $_SESSION['pack_checkout_pack_id']);
            error_log('PaymentService pack error: ' . $e->getMessage());
            flash('error', 'Une erreur est survenue lors de la création du paiement. Veuillez réessayer.');
            header('Location: ' . APP_BASE_URL . '/pack.php?id=' . $packId);
            exit;
        }

        header('Location: ' . $checkout['url']);
        exit;
    }
}

$pageTitle = 'Réserver – ' . $pack['title'];
$navContext = 'sessions';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🛒 Réserver un pack</h1>

    <div class="booking-summary">
        <h2><?= e($pack['title']) ?></h2>
        <p>💶 <?= e(formatPrice((int) $pack['price_cents'])) ?> — <?= count($sessions) ?> séance(s) incluse(s)</p>
        <ul style="list-style:none;padding:0;margin:.5rem 0 0">
            <?php foreach ($sessions as $s): ?>
                <li style="font-size:.9rem;color:var(--color-muted)">
                    📅 <?= e($s['title']) ?> — <?= e(formatDate($s['session_date'])) ?>
                    <?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="form-card" style="max-width:600px">
        <?php if (!empty($errors)): ?>
            <ul class="alert alert--error">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

            <p><strong>Réservé par :</strong> <?= e($user['first_name'] . ' ' . $user['last_name']) ?></p>
            <p class="mt-1"><strong>E-mail :</strong> <?= e($user['email']) ?></p>

            <hr class="mt-2 mb-2">
            <h3 class="form-section-title">Informations sur l'enfant</h3>
            <p style="font-size:.9rem;color:var(--color-muted)">Ces informations s'appliqueront à toutes les séances du pack.</p>

            <div class="form-group mt-1">
                <label for="child_first_name">Prénom de l'enfant <span class="required">*</span></label>
                <input type="text" id="child_first_name" name="child_first_name"
                       value="<?= e($childFirstName) ?>" required>
            </div>

            <div class="form-group mt-1">
                <label for="child_last_name">Nom de l'enfant <span class="required">*</span></label>
                <input type="text" id="child_last_name" name="child_last_name"
                       value="<?= e($childLastName) ?>" required>
            </div>

            <div class="form-group mt-1">
                <label for="child_age">Âge de l'enfant <span class="required">*</span></label>
                <input type="number" id="child_age" name="child_age"
                       value="<?= e($childAge) ?>" min="1" max="17" required>
            </div>

            <div class="form-group mt-1">
                <label for="child_allergies">Allergies alimentaires <span class="optional">(optionnel)</span></label>
                <textarea id="child_allergies" name="child_allergies" rows="2"><?= e($childAllergies) ?></textarea>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn--primary">
                    💳 Procéder au paiement (<?= e(formatPrice((int) $pack['price_cents'])) ?>)
                </button>
                <a href="<?= APP_BASE_URL ?>/pack.php?id=<?= $packId ?>" class="btn btn--secondary" style="margin-left:.5rem">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
