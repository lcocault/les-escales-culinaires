<?php
// public/book.php – booking form (payment redirect via configured provider)
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

if ((int) $session['remaining_seats'] <= 0) {
    flash('error', 'Cette séance est complète.');
    header('Location: ' . APP_BASE_URL . '/ateliers/seance.php?id=' . $sessionId);
    exit;
}

if (!empty($session['is_private'])) {
    if (!Auth::isAdmin() && !$sessionModel->isUserAllowed($sessionId, Auth::currentUserId())) {
        flash('error', 'Vous n\'êtes pas autorisé(e) à réserver cette séance privée.');
        header('Location: ' . APP_BASE_URL . '/ateliers/seance.php?id=' . $sessionId);
        exit;
    }
}

if (strtotime($session['session_date'] . ' ' . $session['end_time']) < time()) {
    flash('error', 'Cette séance est passée.');
    header('Location: ' . APP_BASE_URL . '/ateliers/seance.php?id=' . $sessionId);
    exit;
}

$bookingModel = new BookingModel();
$existing = $bookingModel->findByUserAndSession(Auth::currentUserId(), $sessionId);
if ($existing && in_array($existing['status'], ['confirmed', 'attended'])) {
    flash('info', 'Vous avez déjà réservé cette séance.');
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}
if ($existing && $existing['status'] === 'pending') {
    // Resume payment for the existing pending booking (using the stored discount)
    $resumePrice = max(0, (int) $session['price_cents'] - (int) $existing['discount_cents']);
    try {
        $checkout = PaymentService::createCheckoutUrl(
            (int) $existing['id'],
            $session['title'],
            $resumePrice,
            'eur'
        );
    } catch (RuntimeException $e) {
        error_log('PaymentService error: ' . $e->getMessage());
        flash('error', 'Une erreur est survenue lors de la création du paiement. Veuillez réessayer.');
        header('Location: ' . APP_BASE_URL . '/ateliers/seance.php?id=' . $sessionId);
        exit;
    }
    if (!empty($checkout['squareOrderId'])) {
        $bookingModel->storePaymentRef((int) $existing['id'], 'sq_order_' . $checkout['squareOrderId']);
    }
    header('Location: ' . $checkout['url']);
    exit;
}

$userModel = new UserModel();
$user = $userModel->findById(Auth::currentUserId());
$useCredit = false;
$errors = [];
$childFirstName  = '';
$childLastName   = $user['last_name'] ?? '';
$childAge        = '';
$childAllergies  = '';
$numberOfChildren = 1;
$promoCode      = '';
$appliedPromo   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $childFirstName  = trim($_POST['child_first_name']   ?? '');
    $childLastName   = trim($_POST['child_last_name']    ?? '');
    $childAge        = trim($_POST['child_age']          ?? '');
    $childAllergies  = trim($_POST['child_allergies']    ?? '');
    $numberOfChildren = max(1, (int) ($_POST['number_of_children'] ?? 1));
    $promoCode       = strtoupper(trim($_POST['promo_code'] ?? ''));

    if ($childFirstName === '') {
        $errors[] = 'Le prénom de l\'enfant est obligatoire.';
    }
    if ($childLastName === '') {
        $errors[] = 'Le nom de l\'enfant est obligatoire.';
    }
    if ($childAge === '' || !ctype_digit($childAge) || (int) $childAge < 1 || (int) $childAge > 17) {
        $errors[] = 'L\'âge de l\'enfant doit être un nombre entier entre 1 et 17.';
    }
    if ($numberOfChildren < 1 || $numberOfChildren > (int) $session['remaining_seats']) {
        $errors[] = 'Le nombre d\'enfants doit être compris entre 1 et ' . (int) $session['remaining_seats'] . '.';
    }

    // Validate promo code if provided
    if ($promoCode !== '') {
        $promoModel   = new PromoCodeModel();
        $appliedPromo = $promoModel->validateForSession($promoCode, $sessionId);
        if ($appliedPromo === null) {
            $errors[] = 'Le code promotionnel est invalide ou n\'est pas applicable à cette séance.';
        }
    }

    if (empty($errors)) {
        $useCredit = isset($_POST['use_credit']) && (int) $user['credits'] > 0;
        $action    = $_POST['action'] ?? 'pay';

        $discountCents = $appliedPromo ? min((int) $appliedPromo['discount_cents'], (int) $session['price_cents']) : 0;
        $finalPrice    = (int) $session['price_cents'] - $discountCents;

        if ($action === 'check_promo') {
            // Just re-render the form with the discount information visible.
            // Fall through to the HTML rendering below without creating a booking.
        } elseif ($action === 'basket') {
            // Add to basket and redirect to basket page
            $basketModel = new BasketModel();
            $basketModel->addItem(
                Auth::currentUserId(),
                $sessionId,
                $childFirstName,
                $childLastName,
                (int) $childAge,
                $childAllergies,
                $numberOfChildren
            );
            flash('success', '🛒 Séance ajoutée au panier !');
            header('Location: ' . APP_BASE_URL . '/basket.php');
            exit;
        } else {
        // Create the booking record (status = pending)
        $bookingId = $bookingModel->create(
            Auth::currentUserId(),
            $sessionId,
            $useCredit,
            $childFirstName,
            $childLastName,
            (int) $childAge,
            $childAllergies,
            $appliedPromo ? (int) $appliedPromo['id'] : null,
            $discountCents,
            $numberOfChildren
        );

        // Increment promo code usage counter
        if ($appliedPromo) {
            (new PromoCodeModel())->incrementUsedCount((int) $appliedPromo['id']);
        }

        if ($useCredit) {
            // Free booking via credit
            $bookingModel->confirm($bookingId, 'credit');
            $userModel->updateCredits(Auth::currentUserId(), -1);
            $sessionModel->decrementSeats($sessionId, $numberOfChildren);
            Mailer::sendBookingConfirmationToAttendee($user, $session);
            Mailer::sendBookingNotificationToAdmin($user, $session);
            flash('success', 'Réservation confirmée avec votre crédit !');
            header('Location: ' . APP_BASE_URL . '/my-sessions.php');
            exit;
        }

        // Redirect to payment provider checkout
        try {
            $checkout = PaymentService::createCheckoutUrl(
                $bookingId,
                $session['title'],
                $finalPrice,
                'eur'
            );
        } catch (RuntimeException $e) {
            error_log('PaymentService error: ' . $e->getMessage());
            flash('error', 'Une erreur est survenue lors de la création du paiement. Veuillez réessayer.');
            header('Location: ' . APP_BASE_URL . '/ateliers/seance.php?id=' . $sessionId);
            exit;
        }

        // Store the Square order ID before redirecting so we can refund later if needed.
        if (!empty($checkout['squareOrderId'])) {
            $bookingModel->storePaymentRef($bookingId, 'sq_order_' . $checkout['squareOrderId']);
        }

        header('Location: ' . $checkout['url']);
        exit;
        } // end else (pay action)
    } // end if (empty($errors))
} // end if POST

$pageTitle = 'Réserver – ' . $session['title'];
$navContext = 'sessions';
include ROOT_DIR . '/templates/header.php';

// Load packs for this session (to show a banner) – single query with availability
$packModel      = new PackModel();
$sessionPacks   = $packModel->getPacksForSessionWithAvailability($sessionId);
$availablePacks = array_filter($sessionPacks, fn($p) => (int) $p['is_available'] === 1);
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🛒 Réserver une séance</h1>

    <?php if (!empty($availablePacks)): ?>
        <div class="flash flash--info" style="margin-bottom:1.5rem">
            💡 Cette séance fait partie <?= count($availablePacks) === 1 ? 'd\'un pack' : 'de packs' ?> :
            <?php foreach ($availablePacks as $pk): ?>
                <strong><a href="<?= APP_BASE_URL ?>/pack.php?id=<?= (int) $pk['id'] ?>"><?= e($pk['title']) ?></a></strong>
                (<?= e(formatPrice((int) $pk['price_cents'])) ?>)<?= $pk !== end($availablePacks) ? ', ' : '' ?>
            <?php endforeach; ?>
            — réserver le pack vous inscrit à toutes les séances incluses.
        </div>
    <?php endif; ?>

    <div class="booking-summary">
        <h2><?= e($session['title']) ?></h2>
        <p>📅 <?= e(formatDate($session['session_date'])) ?>
           &nbsp; ⏰ <?= e(substr($session['start_time'], 0, 5)) ?> – <?= e(substr($session['end_time'], 0, 5)) ?>
           &nbsp; 💶 <?= e(formatPrice((int) $session['price_cents'])) ?>
        </p>
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

            <div class="form-group mt-1">
                <label for="number_of_children">Nombre d'enfants <span class="required">*</span></label>
                <input type="number" id="number_of_children" name="number_of_children"
                       value="<?= (int) $numberOfChildren ?>" min="1" max="<?= (int) $session['remaining_seats'] ?>" required>
                <small style="color:var(--color-muted)">Places restantes : <?= (int) $session['remaining_seats'] ?></small>
            </div>

            <?php if ((int) $numberOfChildren > 1): ?>
                <p style="font-size:.9rem;color:var(--color-muted)">
                    Renseignez les informations d'un des enfants inscrits (elles s'appliqueront à tous).
                </p>
            <?php endif; ?>

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

            <hr class="mt-2 mb-2">
            <h3 class="form-section-title">Code promotionnel</h3>

            <div class="form-group mt-1">
                <label for="promo_code">Code promo <span class="optional">(optionnel)</span></label>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <input type="text" id="promo_code" name="promo_code"
                           value="<?= e($promoCode) ?>"
                           placeholder="ex. : PROMO10"
                           style="text-transform:uppercase;flex:1"
                           autocomplete="off">
                    <button type="submit" name="action" value="check_promo" class="btn btn--secondary" style="white-space:nowrap">
                        Vérifier
                    </button>
                </div>
            </div>

            <?php if ($appliedPromo): ?>
                <?php
                    $discountCentsDisplay = min((int) $appliedPromo['discount_cents'], (int) $session['price_cents']);
                    $finalPriceDisplay    = (int) $session['price_cents'] - $discountCentsDisplay;
                ?>
                <div class="flash flash--success" style="margin-top:.5rem" id="promo-result">
                    🎉 Code promotionnel appliqué : –<?= e(formatPrice($discountCentsDisplay)) ?>.
                    Prix final : <strong><?= e(formatPrice($finalPriceDisplay)) ?></strong>
                </div>
            <?php else: ?>
                <div id="promo-result" style="margin-top:.5rem;display:none"></div>
            <?php endif; ?>

            <?php if ((int) $user['credits'] > 0): ?>
                <div class="form-group form-group--checkbox mt-2">
                    <input type="checkbox" id="use_credit" name="use_credit" value="1">
                    <label for="use_credit">
                        Utiliser un de mes crédits (<?= (int) $user['credits'] ?> crédit(s) disponible(s)) – réservation gratuite
                    </label>
                </div>
            <?php endif; ?>

            <?php
                $displayPrice = $appliedPromo
                    ? max(0, (int) $session['price_cents'] - min((int) $appliedPromo['discount_cents'], (int) $session['price_cents']))
                    : (int) $session['price_cents'];
            ?>
            <div class="mt-3">
                <button type="submit" name="action" value="pay" class="btn btn--primary" id="pay-btn">
                    💳 Procéder au paiement (<span id="pay-btn-price"><?= e(formatPrice($displayPrice)) ?></span>)
                </button>
                <button type="submit" name="action" value="basket" class="btn btn--warning" style="margin-left:.5rem">
                    🛒 Ajouter au panier
                </button>
                <a href="<?= APP_BASE_URL ?>/ateliers/seance.php?id=<?= $sessionId ?>" class="btn btn--secondary" style="margin-left:.5rem">Annuler</a>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var sessionPriceCents = <?= json_encode((int) $session['price_cents']) ?>;
    var sessionId         = <?= json_encode($sessionId) ?>;
    var validateUrl       = <?= json_encode(APP_BASE_URL . '/promo-validate.php') ?>;

    var promoInput  = document.getElementById('promo_code');
    var promoResult = document.getElementById('promo-result');
    var payBtnPrice = document.getElementById('pay-btn-price');

    if (!promoInput || !promoResult || !payBtnPrice) { return; }

    function formatPrice(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + '\u00a0€';
    }

    var debounceTimer = null;

    function validatePromo() {
        var code = promoInput.value.trim().toUpperCase();
        if (code === '') {
            promoResult.style.display = 'none';
            promoResult.className     = '';
            promoResult.innerHTML     = '';
            payBtnPrice.textContent   = formatPrice(sessionPriceCents);
            return;
        }

        fetch(validateUrl + '?code=' + encodeURIComponent(code) + '&session_id=' + sessionId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                promoResult.style.display = '';
                if (data.valid) {
                    var discount   = Math.min(data.discount_cents, sessionPriceCents);
                    var finalPrice = sessionPriceCents - discount;
                    promoResult.className = 'flash flash--success';
                    promoResult.innerHTML = '🎉 Code promotionnel appliqué\u00a0: \u2013' + formatPrice(discount) + '.'
                        + ' Prix final\u00a0: <strong>' + formatPrice(finalPrice) + '</strong>';
                    payBtnPrice.textContent = formatPrice(finalPrice);
                } else {
                    promoResult.className = 'flash flash--error';
                    promoResult.innerHTML = '❌ ' + data.message;
                    payBtnPrice.textContent = formatPrice(sessionPriceCents);
                }
            })
            .catch(function () {
                console.error('Promo validation request failed.');
            });
    }

    promoInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(validatePromo, 400);
    });

    promoInput.addEventListener('blur', function () {
        clearTimeout(debounceTimer);
        validatePromo();
    });
}());
</script>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
