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
    header('Location: ' . APP_BASE_URL . '/session.php?id=' . $sessionId);
    exit;
}

if (!empty($session['is_private'])) {
    if (!Auth::isAdmin() && !$sessionModel->isUserAllowed($sessionId, Auth::currentUserId())) {
        flash('error', 'Vous n\'êtes pas autorisé(e) à réserver cette séance privée.');
        header('Location: ' . APP_BASE_URL . '/session.php?id=' . $sessionId);
        exit;
    }
}

if (strtotime($session['session_date'] . ' ' . $session['end_time']) < time()) {
    flash('error', 'Cette séance est passée.');
    header('Location: ' . APP_BASE_URL . '/session.php?id=' . $sessionId);
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
    // Resume payment for the existing pending booking
    $nbChildrenExisting   = max(1, (int) ($existing['nb_children'] ?? 1));
    $extraDiscountEach    = 200; // 2 euros per extra child
    $childrenTotalExisting = (int) $session['price_cents']
        + ($nbChildrenExisting - 1) * max(0, (int) $session['price_cents'] - $extraDiscountEach);
    $resumePrice = max(0, $childrenTotalExisting - (int) $existing['discount_cents']);
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
        header('Location: ' . APP_BASE_URL . '/session.php?id=' . $sessionId);
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
$children = [
    ['first_name' => '', 'last_name' => $user['last_name'] ?? '', 'age' => '', 'allergies' => ''],
];
$promoCode    = '';
$appliedPromo = null;

/** Price (in cents) for each child position (1-based index). */
function childPriceCents(int $basePrice, int $position): int
{
    $extraDiscount = 200; // 2 euros discount for each additional child
    return $position === 1 ? $basePrice : max(0, $basePrice - $extraDiscount);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    // Parse children array from POST
    $rawChildren = $_POST['children'] ?? [];
    if (!is_array($rawChildren) || empty($rawChildren)) {
        $rawChildren = [[]];
    }
    $children = [];
    foreach ($rawChildren as $raw) {
        $children[] = [
            'first_name' => trim($raw['first_name'] ?? ''),
            'last_name'  => trim($raw['last_name']  ?? ''),
            'age'        => trim($raw['age']         ?? ''),
            'allergies'  => trim($raw['allergies']   ?? ''),
        ];
    }

    $promoCode = strtoupper(trim($_POST['promo_code'] ?? ''));

    // Validate each child
    foreach ($children as $idx => $child) {
        $num = $idx + 1;
        if ($child['first_name'] === '') {
            $errors[] = 'Le prénom de l\'enfant ' . $num . ' est obligatoire.';
        }
        if ($child['last_name'] === '') {
            $errors[] = 'Le nom de l\'enfant ' . $num . ' est obligatoire.';
        }
        if ($child['age'] === '' || !ctype_digit($child['age']) || (int) $child['age'] < 1 || (int) $child['age'] > 17) {
            $errors[] = 'L\'âge de l\'enfant ' . $num . ' doit être un nombre entier entre 1 et 17.';
        }
    }

    $nbChildren = count($children);

    // Check seat availability for all children
    if (empty($errors) && (int) $session['remaining_seats'] < $nbChildren) {
        $errors[] = 'Il n\'y a pas assez de places disponibles pour ' . $nbChildren . ' enfant(s). '
                  . 'Places restantes : ' . (int) $session['remaining_seats'] . '.';
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

        // Compute total price for all children
        $basePrice      = (int) $session['price_cents'];
        $childrenTotal  = 0;
        for ($i = 1; $i <= $nbChildren; $i++) {
            $childrenTotal += childPriceCents($basePrice, $i);
        }
        $discountCents = $appliedPromo ? min((int) $appliedPromo['discount_cents'], $childrenTotal) : 0;
        $finalPrice    = $childrenTotal - $discountCents;

        if ($action === 'check_promo') {
            // Just re-render the form with the discount information visible.
            // Fall through to the HTML rendering below without creating a booking.
        } elseif ($action === 'basket') {
            // The basket supports one child per item; multi-child requires direct payment.
            if ($nbChildren > 1) {
                flash('warning', '⚠️ Le panier ne prend en charge qu\'un enfant par séance. '
                    . 'Pour inscrire plusieurs enfants, utilisez le bouton « Procéder au paiement ».');
                header('Location: ' . APP_BASE_URL . '/book.php?session_id=' . $sessionId);
                exit;
            }
            // Add to basket and redirect to basket page
            $basketModel = new BasketModel();
            $basketModel->addItem(
                Auth::currentUserId(),
                $sessionId,
                $children[0]['first_name'],
                $children[0]['last_name'],
                (int) $children[0]['age'],
                $children[0]['allergies']
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
            $children[0]['first_name'],
            $children[0]['last_name'],
            (int) $children[0]['age'],
            $children[0]['allergies'],
            $appliedPromo ? (int) $appliedPromo['id'] : null,
            $discountCents,
            $nbChildren
        );

        // Save extra children (2nd, 3rd, etc.)
        if ($nbChildren > 1) {
            $bookingModel->addExtraChildren($bookingId, array_slice($children, 1));
        }

        // Increment promo code usage counter
        if ($appliedPromo) {
            (new PromoCodeModel())->incrementUsedCount((int) $appliedPromo['id']);
        }

        if ($useCredit) {
            // Free booking via credit
            $bookingModel->confirm($bookingId, 'credit');
            $userModel->updateCredits(Auth::currentUserId(), -1);
            $sessionModel->decrementSeats($sessionId, $nbChildren);
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
            header('Location: ' . APP_BASE_URL . '/session.php?id=' . $sessionId);
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
           &nbsp; 💶 <?= e(formatPrice((int) $session['price_cents'])) ?> par enfant
           <?php if ((int) $session['price_cents'] > 200): ?>
               <span style="color:var(--color-muted);font-size:.9rem">(–2,00&nbsp;€ à partir du 2<sup>e</sup> enfant)</span>
           <?php endif; ?>
        </p>
    </div>

    <div class="form-card" style="max-width:640px">
        <?php if (!empty($errors)): ?>
            <ul class="alert alert--error">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="" id="booking-form">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

            <p><strong>Réservé par :</strong> <?= e($user['first_name'] . ' ' . $user['last_name']) ?></p>
            <p class="mt-1"><strong>E-mail :</strong> <?= e($user['email']) ?></p>

            <hr class="mt-2 mb-2">
            <h3 class="form-section-title">Enfants inscrits</h3>

            <div id="children-container">
            <?php foreach ($children as $idx => $child): ?>
                <div class="child-section" id="child-section-<?= $idx ?>">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                        <h4 style="margin:0">
                            Enfant <?= $idx + 1 ?>
                            <span class="child-price-label" style="font-weight:normal;font-size:.9rem;color:var(--color-muted)">
                                (<?= e(formatPrice(childPriceCents((int) $session['price_cents'], $idx + 1))) ?>)
                            </span>
                        </h4>
                        <?php if ($idx > 0): ?>
                            <button type="button" class="btn btn--danger btn--sm remove-child-btn"
                                    data-index="<?= $idx ?>" style="margin-left:1rem">
                                Supprimer
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mt-1">
                        <label>Prénom <span class="required">*</span></label>
                        <input type="text" name="children[<?= $idx ?>][first_name]"
                               value="<?= e($child['first_name']) ?>" required>
                    </div>

                    <div class="form-group mt-1">
                        <label>Nom <span class="required">*</span></label>
                        <input type="text" name="children[<?= $idx ?>][last_name]"
                               value="<?= e($child['last_name']) ?>" required>
                    </div>

                    <div class="form-group mt-1">
                        <label>Âge <span class="required">*</span></label>
                        <input type="number" name="children[<?= $idx ?>][age]"
                               value="<?= e($child['age']) ?>" min="1" max="17" required>
                    </div>

                    <div class="form-group mt-1">
                        <label>Allergies alimentaires <span class="optional">(optionnel)</span></label>
                        <textarea name="children[<?= $idx ?>][allergies]" rows="2"><?= e($child['allergies']) ?></textarea>
                    </div>

                    <?php if ($idx < count($children) - 1): ?>
                        <hr style="margin:1rem 0">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>

            <?php if ((int) $session['remaining_seats'] > 1): ?>
                <div class="mt-2">
                    <button type="button" id="add-child-btn" class="btn btn--secondary btn--sm">
                        ＋ Ajouter un enfant
                        <?php if ((int) $session['price_cents'] > 200): ?>
                            (–2,00&nbsp;€)
                        <?php endif; ?>
                    </button>
                </div>
            <?php endif; ?>

            <div id="children-total" class="mt-2" style="font-size:.95rem;color:var(--color-muted)"></div>

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
                    $basePrice = (int) $session['price_cents'];
                    $nbChildrenDisplay = count($children);
                    $childrenTotalDisplay = 0;
                    for ($i = 1; $i <= $nbChildrenDisplay; $i++) {
                        $childrenTotalDisplay += childPriceCents($basePrice, $i);
                    }
                    $discountCentsDisplay = min((int) $appliedPromo['discount_cents'], $childrenTotalDisplay);
                    $finalPriceDisplay    = $childrenTotalDisplay - $discountCentsDisplay;
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
                $basePrice = (int) $session['price_cents'];
                $nbChildrenDisplay = count($children);
                $displayTotal = 0;
                for ($i = 1; $i <= $nbChildrenDisplay; $i++) {
                    $displayTotal += childPriceCents($basePrice, $i);
                }
                if ($appliedPromo) {
                    $displayTotal = max(0, $displayTotal - min((int) $appliedPromo['discount_cents'], $displayTotal));
                }
            ?>
            <div class="mt-3">
                <button type="submit" name="action" value="pay" class="btn btn--primary" id="pay-btn">
                    💳 Procéder au paiement (<span id="pay-btn-price"><?= e(formatPrice($displayTotal)) ?></span>)
                </button>
                <button type="submit" name="action" value="basket" class="btn btn--warning" style="margin-left:.5rem">
                    🛒 Ajouter au panier
                </button>
                <a href="<?= APP_BASE_URL ?>/session.php?id=<?= $sessionId ?>" class="btn btn--secondary" style="margin-left:.5rem">Annuler</a>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var basePriceCents    = <?= json_encode((int) $session['price_cents']) ?>;
    var maxSeats          = <?= json_encode((int) $session['remaining_seats']) ?>;
    var extraDiscount     = 200; // 2 euros discount per extra child
    var sessionId         = <?= json_encode($sessionId) ?>;
    var validateUrl       = <?= json_encode(APP_BASE_URL . '/promo-validate.php') ?>;

    var container     = document.getElementById('children-container');
    var addBtn        = document.getElementById('add-child-btn');
    var promoInput    = document.getElementById('promo_code');
    var promoResult   = document.getElementById('promo-result');
    var payBtnPrice   = document.getElementById('pay-btn-price');
    var totalDiv      = document.getElementById('children-total');

    var promoDiscountCents = 0;
    var debounceTimer      = null;

    function formatPrice(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + '\u00a0€';
    }

    function childPriceCents(position) {
        return position === 1 ? basePriceCents : Math.max(0, basePriceCents - extraDiscount);
    }

    function countChildren() {
        return container.querySelectorAll('.child-section').length;
    }

    function calcTotal() {
        var nb = countChildren();
        var total = 0;
        for (var i = 1; i <= nb; i++) { total += childPriceCents(i); }
        return Math.max(0, total - promoDiscountCents);
    }

    function updateTotalDisplay() {
        var nb = countChildren();
        if (nb <= 1) {
            totalDiv.textContent = '';
            return;
        }
        var parts = [];
        for (var i = 1; i <= nb; i++) {
            parts.push('Enfant\u00a0' + i + '\u00a0: ' + formatPrice(childPriceCents(i)));
        }
        totalDiv.innerHTML = parts.join(' + ')
            + (promoDiscountCents > 0 ? ' \u2013\u00a0' + formatPrice(promoDiscountCents) + ' (promo)' : '')
            + ' = <strong>' + formatPrice(calcTotal()) + '</strong>';
    }

    function updatePayBtn() {
        if (payBtnPrice) { payBtnPrice.textContent = formatPrice(calcTotal()); }
        updateTotalDisplay();
    }

    function renumberChildren() {
        var sections = container.querySelectorAll('.child-section');
        sections.forEach(function (section, i) {
            // Update heading number
            var h4 = section.querySelector('h4');
            if (h4) {
                var priceLabel = h4.querySelector('.child-price-label');
                var priceTxt   = priceLabel ? priceLabel.outerHTML : '';
                h4.innerHTML   = 'Enfant\u00a0' + (i + 1) + '\u00a0' + priceTxt;
                // Rebuild price label
                var lbl = h4.querySelector('.child-price-label');
                if (lbl) { lbl.textContent = '(' + formatPrice(childPriceCents(i + 1)) + ')'; }
            }
            // Rename inputs
            section.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/children\[\d+\]/, 'children[' + i + ']');
            });
            // Update remove button data-index and visibility
            var rmBtn = section.querySelector('.remove-child-btn');
            if (rmBtn) {
                rmBtn.setAttribute('data-index', i);
                rmBtn.style.display = i === 0 ? 'none' : '';
            }
            // Update section id
            section.id = 'child-section-' + i;
        });
    }

    function addChildSection() {
        var idx = countChildren();
        if (maxSeats > 0 && idx >= maxSeats) { return; }

        var section = document.createElement('div');
        section.className = 'child-section';
        section.id = 'child-section-' + idx;

        var priceCents = childPriceCents(idx + 1);
        section.innerHTML =
            '<hr style="margin:1rem 0">'
            + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
            +   '<h4 style="margin:0">Enfant\u00a0' + (idx + 1) + '\u00a0'
            +     '<span class="child-price-label" style="font-weight:normal;font-size:.9rem;color:var(--color-muted)">(' + formatPrice(priceCents) + ')</span>'
            +   '</h4>'
            +   '<button type="button" class="btn btn--danger btn--sm remove-child-btn" data-index="' + idx + '" style="margin-left:1rem">Supprimer</button>'
            + '</div>'
            + '<div class="form-group mt-1"><label>Prénom <span class="required">*</span></label>'
            +   '<input type="text" name="children[' + idx + '][first_name]" required></div>'
            + '<div class="form-group mt-1"><label>Nom <span class="required">*</span></label>'
            +   '<input type="text" name="children[' + idx + '][last_name]" required></div>'
            + '<div class="form-group mt-1"><label>Âge <span class="required">*</span></label>'
            +   '<input type="number" name="children[' + idx + '][age]" min="1" max="17" required></div>'
            + '<div class="form-group mt-1"><label>Allergies alimentaires <span class="optional">(optionnel)</span></label>'
            +   '<textarea name="children[' + idx + '][allergies]" rows="2"></textarea></div>';

        container.appendChild(section);

        // Bind remove button
        section.querySelector('.remove-child-btn').addEventListener('click', removeChildSection);

        // Show/hide add button based on seat count
        if (addBtn) {
            addBtn.style.display = (countChildren() >= maxSeats) ? 'none' : '';
        }
        updatePayBtn();
    }

    function removeChildSection(e) {
        var btn = e.currentTarget;
        var idx = parseInt(btn.getAttribute('data-index'), 10);
        if (idx === 0) { return; } // never remove the first child
        var section = document.getElementById('child-section-' + idx);
        if (section) { section.parentNode.removeChild(section); }
        renumberChildren();
        if (addBtn) { addBtn.style.display = ''; }
        updatePayBtn();
    }

    // Bind existing remove buttons (server-rendered extra children on validation error)
    container.querySelectorAll('.remove-child-btn').forEach(function (btn) {
        btn.addEventListener('click', removeChildSection);
    });

    if (addBtn) { addBtn.addEventListener('click', addChildSection); }

    // Initial display update
    updatePayBtn();

    // ── Promo validation ─────────────────────────────────────────────────────

    if (!promoInput || !promoResult || !payBtnPrice) { return; }

    function validatePromo() {
        var code = promoInput.value.trim().toUpperCase();
        if (code === '') {
            promoDiscountCents = 0;
            promoResult.style.display = 'none';
            promoResult.className     = '';
            promoResult.innerHTML     = '';
            updatePayBtn();
            return;
        }

        fetch(validateUrl + '?code=' + encodeURIComponent(code) + '&session_id=' + sessionId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                promoResult.style.display = '';
                if (data.valid) {
                    var nb = countChildren();
                    var total = 0;
                    for (var i = 1; i <= nb; i++) { total += childPriceCents(i); }
                    promoDiscountCents = Math.min(data.discount_cents, total);
                    var finalTotal = Math.max(0, total - promoDiscountCents);
                    promoResult.className = 'flash flash--success';
                    promoResult.innerHTML = '🎉 Code promotionnel appliqué\u00a0: \u2013' + formatPrice(promoDiscountCents) + '.'
                        + ' Prix final\u00a0: <strong>' + formatPrice(finalTotal) + '</strong>';
                } else {
                    promoDiscountCents = 0;
                    promoResult.className = 'flash flash--error';
                    promoResult.innerHTML = '❌ ' + data.message;
                }
                updatePayBtn();
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
