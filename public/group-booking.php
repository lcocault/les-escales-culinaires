<?php
// public/group-booking.php – form for requesting a private group session (birthday party)
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$userModel = new UserModel();
$user      = $userModel->findById(Auth::currentUserId());

// Optional: booking linked to a specific group session slot
$slotId = isset($_POST['slot_id'])
    ? (int) $_POST['slot_id']
    : (isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0);
$slot   = null;
if ($slotId > 0) {
    $slotModel = new GroupSessionSlotModel();
    $slot = $slotModel->findById($slotId);
    if (!$slot || $slot['status'] === 'cancelled' || (int) $slot['remaining_groups'] <= 0) {
        flash('error', 'Ce créneau n\'est plus disponible.');
        header('Location: ' . APP_BASE_URL . '/');
        exit;
    }
}

$errors         = [];
$contactPhone   = $user['phone'] ?? '';
$nbChildren     = '';
$childrenAges   = '';
$preferredDate  = $slot ? $slot['slot_date'] : '';
$locationType   = 'escales';
$locationAddress = '';
$allergies      = '';
$additionalInfo = '';

$minDate = date('Y-m-d', strtotime('+' . GroupBookingModel::MIN_ADVANCE_DAYS . ' days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $contactPhone    = trim($_POST['contact_phone']    ?? '');
    $nbChildren      = trim($_POST['nb_children']      ?? '');
    $childrenAges    = trim($_POST['children_ages']    ?? '');
    $preferredDate   = trim($_POST['preferred_date']   ?? '');
    $locationType    = trim($_POST['location_type']    ?? 'escales');
    $locationAddress = trim($_POST['location_address'] ?? '');
    $allergies       = trim($_POST['allergies']        ?? '');
    $additionalInfo  = trim($_POST['additional_info']  ?? '');

    // Validation
    if ($nbChildren === '' || !ctype_digit($nbChildren)
        || (int) $nbChildren < GroupBookingModel::MIN_CHILDREN
        || (int) $nbChildren > GroupBookingModel::MAX_CHILDREN
    ) {
        $errors[] = 'Le nombre d\'enfants doit être compris entre '
            . GroupBookingModel::MIN_CHILDREN . ' et ' . GroupBookingModel::MAX_CHILDREN . '.';
    }

    if ($preferredDate === '') {
        $errors[] = 'La date souhaitée est obligatoire.';
    } elseif (!$slot && strtotime($preferredDate) < strtotime($minDate)) {
        // When linked to a slot, skip the min-advance-days check (admin set the date)
        $errors[] = 'La date souhaitée doit être au moins '
            . GroupBookingModel::MIN_ADVANCE_DAYS . ' jours à l\'avance (à partir du '
            . date('d/m/Y', strtotime($minDate)) . ').';
    }

    if (!in_array($locationType, ['home', 'escales'], true)) {
        $errors[] = 'Le type de lieu est invalide.';
    }

    if ($locationType === 'home' && $locationAddress === '') {
        $errors[] = 'L\'adresse de la fête est obligatoire si l\'atelier se déroule à domicile.';
    }

    if (empty($errors)) {
        $model = new GroupBookingModel();
        $id = $model->create([
            'user_id'                => Auth::currentUserId(),
            'group_session_slot_id'  => $slot ? (int) $slot['id'] : null,
            'contact_phone'          => $contactPhone,
            'nb_children'            => (int) $nbChildren,
            'children_ages'          => $childrenAges,
            'preferred_date'         => $preferredDate,
            'location_type'          => $locationType,
            'location_address'       => $locationAddress,
            'allergies'              => $allergies,
            'additional_info'        => $additionalInfo,
        ]);

        // Decrement remaining groups on the slot when a booking is created
        if ($slot) {
            (new GroupSessionSlotModel())->decrementGroups((int) $slot['id']);
        }

        $request = $model->findById($id);

        // Send emails
        Mailer::sendGroupBookingRequestToUser($user, $request);
        Mailer::sendGroupBookingRequestToAdmin($user, $request);

        flash('success', '🎉 Votre demande a bien été envoyée ! Nous vous répondrons dans les plus brefs délais.');
        header('Location: ' . APP_BASE_URL . '/my-group-bookings.php');
        exit;
    }
}

// Calculate price estimates for display
// When linked to a slot, use the slot-specific prices; otherwise fall back to the default constants.
$priceHome    = $slot ? (int) $slot['price_per_child_home_cents']    : GroupBookingModel::PRICE_HOME_CENTS;
$priceEscales = $slot ? (int) $slot['price_per_child_escales_cents'] : GroupBookingModel::PRICE_ESCALES_CENTS;

$pageTitle  = 'Réserver un atelier anniversaire';
$navContext = 'group-booking';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🎂 Réserver un atelier anniversaire</h1>

    <div class="section-block" style="max-width:700px">
        <p>Offrez à votre enfant et à ses amis un moment inoubliable aux fourneaux !
           Un atelier culinaire privé de 2 heures, animé par nos équipes, pour <strong>4 à 8 enfants</strong>.</p>

        <ul style="margin:1rem 0;padding-left:1.4rem;line-height:1.8">
            <li>🏠 <strong>À domicile</strong> : <?= e(formatPrice($priceHome)) ?> par enfant</li>
            <li>📍 <strong>Aux Escales Culinaires</strong> (36 rue Boieldieu, 31300 Toulouse) : <?= e(formatPrice($priceEscales)) ?> par enfant</li>
            <li>📅 Réservation au minimum <strong><?= GroupBookingModel::MIN_ADVANCE_DAYS ?> jours à l'avance</strong></li>
            <li>🥜 Le menu est adapté aux allergies des enfants</li>
        </ul>

        <p style="color:var(--color-muted);font-size:.9rem">
            Après réception de votre demande, nous vous contacterons pour confirmer la disponibilité et finaliser le menu.
        </p>
    </div>

    <?php if ($errors): ?>
        <div class="flash flash--error" style="max-width:700px">
            <ul style="margin:0;padding-left:1.2rem">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-card" style="max-width:700px">
        <?php if ($slot): ?>
            <div class="flash flash--info" style="margin-bottom:1rem">
                📅 <strong>Créneau sélectionné :</strong> <?= e($slot['title']) ?>
                – <?= e(formatDate($slot['slot_date'])) ?>
                – <?= e(substr($slot['start_time'], 0, 5)) ?> – <?= e(substr($slot['end_time'], 0, 5)) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
            <?php if ($slot): ?>
                <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
            <?php endif; ?>

            <p><strong>Compte :</strong> <?= e($user['first_name'] . ' ' . $user['last_name']) ?> (<?= e($user['email']) ?>)</p>

            <hr class="mt-2 mb-2">
            <h3 class="form-section-title">Coordonnées</h3>

            <div class="form-group mt-1">
                <label for="contact_phone">Téléphone de contact</label>
                <input type="tel" id="contact_phone" name="contact_phone"
                       value="<?= e($contactPhone) ?>" placeholder="06 00 00 00 00">
            </div>

            <hr class="mt-2 mb-2">
            <h3 class="form-section-title">L'atelier</h3>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="form-group mt-1">
                    <label for="nb_children">Nombre d'enfants <span class="required">*</span></label>
                    <input type="number" id="nb_children" name="nb_children"
                           min="<?= GroupBookingModel::MIN_CHILDREN ?>"
                           max="<?= GroupBookingModel::MAX_CHILDREN ?>"
                           value="<?= e($nbChildren) ?>" required>
                    <p class="form-hint"><?= GroupBookingModel::MIN_CHILDREN ?> à <?= GroupBookingModel::MAX_CHILDREN ?> enfants</p>
                </div>
                <div class="form-group mt-1">
                    <label for="preferred_date">Date souhaitée <span class="required">*</span></label>
                    <?php if ($slot): ?>
                        <input type="date" id="preferred_date" name="preferred_date"
                               value="<?= e($slot['slot_date']) ?>" readonly
                               style="background:var(--color-bg-alt,#fef9f0)">
                        <p class="form-hint">Créneau fixé par l'organisateur</p>
                    <?php else: ?>
                        <input type="date" id="preferred_date" name="preferred_date"
                               min="<?= e($minDate) ?>"
                               value="<?= e($preferredDate) ?>" required>
                        <p class="form-hint">Au moins <?= GroupBookingModel::MIN_ADVANCE_DAYS ?> jours à l'avance</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group mt-1">
                <label for="children_ages">Âges des enfants <span class="optional">(optionnel)</span></label>
                <input type="text" id="children_ages" name="children_ages"
                       value="<?= e($childrenAges) ?>"
                       placeholder="ex. : 7, 7, 8, 9, 10 ans">
            </div>

            <hr class="mt-2 mb-2">
            <h3 class="form-section-title">Lieu de l'atelier</h3>

            <div class="form-group mt-1">
                <label>Type de lieu <span class="required">*</span></label>
                <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.4rem">
                    <label class="radio-label">
                        <input type="radio" name="location_type" value="escales"
                               <?= $locationType === 'escales' ? 'checked' : '' ?> required
                               onchange="toggleAddressField(this.value)">
                        📍 Aux Escales Culinaires – <strong><?= e(formatPrice($priceEscales)) ?>/enfant</strong>
                        <span style="color:var(--color-muted);font-size:.85rem">– 36 rue Boieldieu, 31300 Toulouse</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="location_type" value="home"
                               <?= $locationType === 'home' ? 'checked' : '' ?>
                               onchange="toggleAddressField(this.value)">
                        🏠 À votre domicile – <strong><?= e(formatPrice($priceHome)) ?>/enfant</strong>
                    </label>
                </div>
            </div>

            <div class="form-group mt-1" id="address-field" style="<?= $locationType !== 'home' ? 'display:none' : '' ?>">
                <label for="location_address">Adresse de la fête <span class="required">*</span></label>
                <textarea id="location_address" name="location_address" rows="2"
                          placeholder="Numéro, rue, code postal, ville"><?= e($locationAddress) ?></textarea>
            </div>

            <hr class="mt-2 mb-2">
            <h3 class="form-section-title">Menu &amp; informations complémentaires</h3>

            <div class="form-group mt-1">
                <label for="allergies">Allergies alimentaires <span class="optional">(optionnel)</span></label>
                <textarea id="allergies" name="allergies" rows="2"
                          placeholder="ex. : 2 enfants allergiques aux noix, 1 enfant intolérant au lactose"><?= e($allergies) ?></textarea>
                <p class="form-hint">Le menu sera adapté en conséquence.</p>
            </div>

            <div class="form-group mt-1">
                <label for="additional_info">Informations complémentaires <span class="optional">(optionnel)</span></label>
                <textarea id="additional_info" name="additional_info" rows="3"
                          placeholder="Thème souhaité, préférences, questions…"><?= e($additionalInfo) ?></textarea>
            </div>

            <div class="mt-3" id="price-estimate" style="background:var(--color-bg-alt,#fef9f0);border:1px solid var(--color-border,#e0d4b0);border-radius:8px;padding:1rem">
                <p style="margin:0;font-weight:600">💶 Tarif estimé : <span id="price-total">–</span></p>
                <p style="margin:.25rem 0 0;font-size:.85rem;color:var(--color-muted)">
                    Le tarif définitif sera confirmé lors de l'échange avec notre équipe.
                </p>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn--primary">📨 Envoyer ma demande</button>
                <a href="<?= $slot ? APP_BASE_URL . '/group-session-slot.php?id=' . (int) $slot['id'] : APP_BASE_URL . '/' ?>" class="btn btn--secondary" style="margin-left:.5rem">Annuler</a>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var priceHome    = <?= json_encode($priceHome) ?>;
    var priceEscales = <?= json_encode($priceEscales) ?>;

    function formatPrice(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + '\u00a0€';
    }

    function updatePriceEstimate() {
        var nbInput  = document.getElementById('nb_children');
        var nb       = parseInt(nbInput ? nbInput.value : '', 10);
        var locRadios = document.querySelectorAll('input[name="location_type"]');
        var locType  = 'escales';
        locRadios.forEach(function (r) { if (r.checked) { locType = r.value; } });

        var totalEl = document.getElementById('price-total');
        if (!totalEl) { return; }

        if (!isNaN(nb) && nb >= <?= GroupBookingModel::MIN_CHILDREN ?> && nb <= <?= GroupBookingModel::MAX_CHILDREN ?>) {
            var unit  = locType === 'home' ? priceHome : priceEscales;
            var total = nb * unit;
            totalEl.textContent = formatPrice(unit) + ' × ' + nb + ' = ' + formatPrice(total);
        } else {
            totalEl.textContent = '–';
        }
    }

    window.toggleAddressField = function (value) {
        var field = document.getElementById('address-field');
        if (field) { field.style.display = value === 'home' ? '' : 'none'; }
        updatePriceEstimate();
    };

    var nbInput = document.getElementById('nb_children');
    if (nbInput) { nbInput.addEventListener('input', updatePriceEstimate); }

    document.querySelectorAll('input[name="location_type"]').forEach(function (r) {
        r.addEventListener('change', updatePriceEstimate);
    });

    updatePriceEstimate();
}());
</script>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
