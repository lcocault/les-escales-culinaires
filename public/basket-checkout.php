<?php
// public/basket-checkout.php – create pending bookings for basket items and redirect to payment
require_once __DIR__ . '/init.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/basket.php');
    exit;
}

Auth::verifyCsrf();

$basketModel  = new BasketModel();
$sessionModel = new SessionModel();
$bookingModel = new BookingModel();
$userModel    = new UserModel();

$items = $basketModel->getByUser(Auth::currentUserId());

if (empty($items)) {
    flash('error', 'Votre panier est vide.');
    header('Location: ' . APP_BASE_URL . '/basket.php');
    exit;
}

// Validate all items before creating any bookings
$errors = [];
foreach ($items as $item) {
    $sessionId = (int) $item['session_id'];

    if (($item['session_status'] ?? '') === 'cancelled') {
        $errors[] = '« ' . $item['title'] . ' » a été annulée.';
        continue;
    }

    if (sessionIsPast($item)) {
        $errors[] = '« ' . $item['title'] . ' » est une séance passée.';
        continue;
    }

    if ((int) $item['remaining_seats'] <= 0) {
        $errors[] = '« ' . $item['title'] . ' » est complète.';
        continue;
    }

    $numberOfChildren = (int) ($item['number_of_children'] ?? 1);
    if ($numberOfChildren > (int) $item['remaining_seats']) {
        $errors[] = '« ' . $item['title'] . ' » n\'a pas assez de places disponibles pour ' . $numberOfChildren . ' enfant(s).';
        continue;
    }

    $existing = $bookingModel->findByUserAndSession(Auth::currentUserId(), $sessionId);
    if ($existing && in_array($existing['status'], ['confirmed', 'attended', 'pending'])) {
        $errors[] = 'Vous avez déjà réservé « ' . $item['title'] . ' ».';
    }
}

if (!empty($errors)) {
    flash('error', 'Impossible de procéder au paiement : ' . implode(' ', $errors));
    header('Location: ' . APP_BASE_URL . '/basket.php');
    exit;
}

// Create pending bookings for each item
$bookingIds = [];
foreach ($items as $item) {
    $bookingId = $bookingModel->create(
        Auth::currentUserId(),
        (int) $item['session_id'],
        false, // no credit for basket checkout
        (string) ($item['child_first_name'] ?? ''),
        (string) ($item['child_last_name']  ?? ''),
        (int) ($item['child_age'] ?? 0),
        (string) ($item['child_allergies']  ?? ''),
        null,
        0,
        (int) ($item['number_of_children'] ?? 1)
    );
    $bookingIds[] = $bookingId;
}

// Store booking IDs in session for payment success/cancel handlers
Auth::start();
$_SESSION['basket_checkout_booking_ids'] = $bookingIds;

// Build line items and compute total
$lineItems  = [];
$totalCents = 0;
foreach ($items as $item) {
    $lineItems[]  = ['name' => $item['title'], 'amount_cents' => (int) $item['price_cents']];
    $totalCents  += (int) $item['price_cents'];
}

// Create payment checkout URL
try {
    $checkout = PaymentService::createBasketCheckoutUrl($lineItems, $totalCents, 'eur');
} catch (RuntimeException $e) {
    // Clean up the pending bookings we just created
    foreach ($bookingIds as $bid) {
        $bookingModel->deleteById($bid);
    }
    unset($_SESSION['basket_checkout_booking_ids']);
    error_log('PaymentService basket error: ' . $e->getMessage());
    flash('error', 'Une erreur est survenue lors de la création du paiement. Veuillez réessayer.');
    header('Location: ' . APP_BASE_URL . '/basket.php');
    exit;
}

// Store Square order ID if applicable
if (!empty($checkout['squareOrderId'])) {
    // Attach the Square order ID to the first booking as a reference
    $bookingModel->storePaymentRef($bookingIds[0], 'sq_order_' . $checkout['squareOrderId']);
}

header('Location: ' . $checkout['url']);
exit;
