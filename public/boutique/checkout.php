<?php
// public/boutique/checkout.php – delivery method selection and payment
require_once __DIR__ . '/../init.php';
Auth::requireLogin();

Auth::start();

$productModel = new ShopProductModel();
$orderModel   = new ShopOrderModel();
$cancelledMarketDates = $orderModel->getCancelledMarketDates();

// Build cart
$cart = $_SESSION['shop_cart'] ?? [];
if (empty($cart)) {
    flash('error', 'Votre panier est vide.');
    header('Location: ' . APP_BASE_URL . '/boutique/cart.php');
    exit;
}

$cartItems  = [];
$itemsCents = 0;
$cartWasAdjustedToMinimum = false;
foreach ($cart as $pid => $qty) {
    $product = $productModel->findById((int) $pid);
    if ($product === null) {
        unset($_SESSION['shop_cart'][$pid]);
        continue;
    }
    $minOrderPortions = max(1, (int) ($product['min_order_portions'] ?? 1));
    $qty     = max($minOrderPortions, (int) $qty);
    if ($qty !== (int) $cart[$pid]) {
        $_SESSION['shop_cart'][$pid] = $qty;
        $cartWasAdjustedToMinimum = true;
    }
    $subtotal = (int) $product['price_cents'] * $qty;
    $itemsCents += $subtotal;
    $cartItems[] = [
        'product_id'      => (int) $product['id'],
        'product_name'    => $product['name'],
        'unit_price_cents'=> (int) $product['price_cents'],
        'quantity'        => $qty,
        'subtotal'        => $subtotal,
    ];
}

if ($cartWasAdjustedToMinimum) {
    flash('info', 'Certaines quantités ont été ajustées pour respecter le minimum de portions par commande.');
}

if (empty($cartItems)) {
    flash('error', 'Votre panier est vide ou contient des produits indisponibles.');
    header('Location: ' . APP_BASE_URL . '/boutique/cart.php');
    exit;
}

$nextMarketWednesday = ShopOrderModel::nextAvailableDate('market_wednesday', null, $cancelledMarketDates);
$nextMarketFriday = ShopOrderModel::nextAvailableDate('market_friday', null, $cancelledMarketDates);

$deliveryMethods = [
    'market_wednesday' => 'Retrait marché Croix-de-Pierre – mercredi ' . formatDate($nextMarketWednesday) . ' (gratuit)',
    'market_friday'    => 'Retrait marché Croix-de-Pierre – vendredi ' . formatDate($nextMarketFriday) . ' (gratuit)',
    'shop'             => 'Retrait en boutique – 36 rue Boieldieu, Toulouse (gratuit)',
    'home'             => 'Livraison à domicile (+' . formatPrice(ShopOrderModel::HOME_DELIVERY_FEE_CENTS) . ')',
];

$errors = [];
$values = [
    'delivery_method'  => 'market_wednesday',
    'delivery_address' => '',
    'delivery_date'    => $nextMarketWednesday,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $values['delivery_method']  = trim($_POST['delivery_method']  ?? '');
    $values['delivery_address'] = trim($_POST['delivery_address'] ?? '');
    $values['delivery_date']    = trim($_POST['delivery_date']     ?? '');

    if (!array_key_exists($values['delivery_method'], $deliveryMethods)) {
        $errors[] = 'Mode de livraison invalide.';
    }

    if (empty($errors) && !ShopOrderModel::validateDeliveryDate($values['delivery_method'], $values['delivery_date'], null, $cancelledMarketDates)) {
        $minDate = ShopOrderModel::nextAvailableDate($values['delivery_method'], null, $cancelledMarketDates);
        if ($values['delivery_method'] === 'market_wednesday') {
            $errors[] = 'La date doit être un mercredi disponible avec au moins ' . ShopOrderModel::MARKET_PREPARATION_HOURS . 'h de préparation (prochain disponible : ' . formatDate($minDate) . ').';
        } elseif ($values['delivery_method'] === 'market_friday') {
            $errors[] = 'La date doit être un vendredi disponible avec au moins ' . ShopOrderModel::MARKET_PREPARATION_HOURS . 'h de préparation (prochain disponible : ' . formatDate($minDate) . ').';
        } else {
            $errors[] = 'La date de livraison doit être au moins ' . ShopOrderModel::MIN_DAYS_BEFORE_DELIVERY . ' jours à partir d\'aujourd\'hui (première date disponible : ' . formatDate($minDate) . ').';
        }
    }

    if ($values['delivery_method'] === 'home' && $values['delivery_address'] === '') {
        $errors[] = 'Veuillez indiquer votre adresse de livraison.';
    }

    if (empty($errors)) {
        // Create the order
        $orderId = $orderModel->create(
            Auth::currentUserId(),
            $values['delivery_method'],
            $values['delivery_date'],
            $values['delivery_address'],
            $cartItems
        );

        $order      = $orderModel->findById($orderId);
        $totalCents = (int) $order['total_cents'];

        // Build line items for payment
        $lineItems = [];
        foreach ($cartItems as $ci) {
            $lineItems[] = [
                'name'         => $ci['product_name'],
                'amount_cents' => $ci['unit_price_cents'],
                'quantity'     => $ci['quantity'],
            ];
        }
        if ((int) $order['delivery_fee_cents'] > 0) {
            $lineItems[] = [
                'name'         => 'Frais de livraison',
                'amount_cents' => (int) $order['delivery_fee_cents'],
                'quantity'     => 1,
            ];
        }

        try {
            $result = PaymentService::createShopOrderCheckoutUrl($orderId, $lineItems, $totalCents, 'eur');
            // Clear cart only after successful redirect to payment
            header('Location: ' . $result['url']);
            exit;
        } catch (\Exception $e) {
            // Remove the pending order on failure
            $orderModel->deletePending($orderId);
            $errors[] = 'Erreur lors de la création du paiement. Veuillez réessayer.';
        }
    }
}

$feeCents   = ($values['delivery_method'] === 'home') ? ShopOrderModel::HOME_DELIVERY_FEE_CENTS : 0;
$totalCents = $itemsCents + $feeCents;

$pageTitle = 'Finaliser la commande';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">📋 Finaliser la commande</h1>

    <?php if ($errors): ?>
        <div class="flash flash--error">
            <ul style="margin:0;padding-left:1.2rem">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start;max-width:900px">

        <!-- Left: form -->
        <form method="post" action="" id="checkout-form">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

            <h2 style="margin-bottom:1rem">Mode de retrait / livraison</h2>
            <p style="color:var(--color-muted);margin-top:0">
                Pour les retraits au marché, votre commande doit être récupérée entre 8h30 et 12h30.
            </p>
            <div class="form-group">
                <?php foreach ($deliveryMethods as $key => $label): ?>
                    <div class="form-group form-group--checkbox" style="margin-bottom:.5rem">
                        <input type="radio" id="dm_<?= $key ?>" name="delivery_method"
                               value="<?= $key ?>"
                               <?= $values['delivery_method'] === $key ? 'checked' : '' ?>
                               onchange="updateDeliveryUI()">
                        <label for="dm_<?= $key ?>"><?= e($label) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Delivery date -->
            <div class="form-group" id="date-group">
                <label for="delivery_date">
                    <span id="date-label">Date souhaitée *</span>
                    <small style="color:var(--color-muted)" id="date-hint">
                        (au moins <?= ShopOrderModel::MIN_DAYS_BEFORE_DELIVERY ?> jours à partir d'aujourd'hui)
                    </small>
                </label>
                <input type="date" id="delivery_date" name="delivery_date" required
                       value="<?= e($values['delivery_date']) ?>">
            </div>

            <!-- Address (home only) -->
            <div class="form-group" id="address-group" style="display:none">
                <label for="delivery_address">Adresse de livraison *</label>
                <textarea id="delivery_address" name="delivery_address"
                          placeholder="Numéro et nom de rue, code postal, ville"><?= e($values['delivery_address']) ?></textarea>
            </div>

            <button type="submit" class="btn btn--primary btn--lg" style="margin-top:1rem">
                Payer <?= formatPrice($totalCents) ?> →
            </button>
        </form>

        <!-- Right: order summary -->
        <div class="section-block">
            <h2 style="margin-bottom:1rem">Récapitulatif</h2>
            <table style="width:100%;font-size:.9rem">
                <tbody>
                    <?php foreach ($cartItems as $ci): ?>
                        <tr>
                            <td><?= e($ci['product_name']) ?> × <?= $ci['quantity'] ?></td>
                            <td style="text-align:right;white-space:nowrap"><?= formatPrice($ci['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="fee-row" style="display:none">
                        <td>Frais de livraison</td>
                        <td style="text-align:right;white-space:nowrap" id="fee-cell"><?= formatPrice(ShopOrderModel::HOME_DELIVERY_FEE_CENTS) ?></td>
                    </tr>
                    <tr style="font-weight:bold;border-top:2px solid var(--color-border)">
                        <td>Total</td>
                        <td style="text-align:right;white-space:nowrap" id="total-cell"><?= formatPrice($totalCents) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    var itemsCents = <?= $itemsCents ?>;
    var feeCents   = <?= ShopOrderModel::HOME_DELIVERY_FEE_CENTS ?>;

    var minDays = <?= ShopOrderModel::MIN_DAYS_BEFORE_DELIVERY ?>;
    var marketPreparationHours = <?= ShopOrderModel::MARKET_PREPARATION_HOURS ?>;

    // Pre-compute next available dates for each method
    var nextDates = {
        'home'             : '<?= ShopOrderModel::nextAvailableDate('home') ?>',
        'market_wednesday' : '<?= ShopOrderModel::nextAvailableDate('market_wednesday', null, $cancelledMarketDates) ?>',
        'market_friday'    : '<?= ShopOrderModel::nextAvailableDate('market_friday', null, $cancelledMarketDates) ?>',
        'shop'             : '<?= ShopOrderModel::nextAvailableDate('shop') ?>'
    };

    function updateDeliveryUI() {
        var method = document.querySelector('input[name="delivery_method"]:checked');
        if (!method) return;
        var deliveryMethod = method.value;

        // Toggle address field
        var addrGroup = document.getElementById('address-group');
        addrGroup.style.display = deliveryMethod === 'home' ? '' : 'none';
        document.getElementById('delivery_address').required = (deliveryMethod === 'home');

        // Update fee row and total
        var isHome = deliveryMethod === 'home';
        document.getElementById('fee-row').style.display = isHome ? '' : 'none';
        var total = itemsCents + (isHome ? feeCents : 0);
        document.getElementById('total-cell').textContent = formatPrice(total);

        // Update submit button text
        var btn = document.querySelector('#checkout-form button[type="submit"]');
        if (btn) btn.textContent = 'Payer ' + formatPrice(total) + ' →';

        // Set minimum date and prefill
        var dateInput = document.getElementById('delivery_date');
        var minDate = nextDates[deliveryMethod] || nextDates['home'];
        dateInput.min = minDate;

        // Update hint
        var hint = document.getElementById('date-hint');
        if (deliveryMethod === 'market_wednesday') {
            hint.textContent = '(mercredi, avec au moins ' + marketPreparationHours + 'h de préparation)';
        } else if (deliveryMethod === 'market_friday') {
            hint.textContent = '(vendredi, avec au moins ' + marketPreparationHours + 'h de préparation)';
        } else {
            hint.textContent = '(au moins ' + minDays + ' jours à partir d\'aujourd\'hui)';
        }

        // Prefill date if current value is invalid
        if (!dateInput.value || dateInput.value < minDate) {
            dateInput.value = minDate;
        }
    }

    function formatPrice(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + ' €';
    }

    // Attach listeners
    document.querySelectorAll('input[name="delivery_method"]').forEach(function(r) {
        r.addEventListener('change', updateDeliveryUI);
    });

    // Run once on load
    updateDeliveryUI();
})();
</script>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
