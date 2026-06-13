<?php
// public/payment_success.php – payment success callback (Stripe or Square).
// In production, verify payment via webhook instead of relying on this redirect.
require_once __DIR__ . '/init.php';
Auth::requireLogin();

Auth::start();
$isBasket = isset($_GET['basket']);
$isPack   = isset($_GET['pack']);

// ── Basket checkout ──────────────────────────────────────────────────────────
if ($isPack) {
    $bookingIds = $_SESSION['pack_checkout_booking_ids'] ?? [];
    unset($_SESSION['pack_checkout_booking_ids'], $_SESSION['pack_checkout_pack_id']);

    if (empty($bookingIds)) {
        flash('success', 'Vos réservations ont été confirmées !');
        header('Location: ' . APP_BASE_URL . '/my-sessions.php');
        exit;
    }

    $isDemo       = isset($_GET['_demo']);
    $bookingModel = new BookingModel();
    $sessionModel = new SessionModel();
    $userModel    = new UserModel();
    $user         = $userModel->findById(Auth::currentUserId());

    $paymentRef = $isDemo
        ? 'demo_pack_' . implode('_', $bookingIds)
        : ($_GET['payment_intent'] ?? $_GET['referenceId'] ?? 'paid_pack_' . implode('_', $bookingIds));

    foreach ($bookingIds as $bookingId) {
        $booking = $bookingModel->findById((int) $bookingId);
        if (!$booking || $booking['status'] !== 'pending') {
            continue;
        }

        $bookingModel->confirm((int) $bookingId, $paymentRef);
        $session = $sessionModel->findById((int) $booking['session_id']);
        $sessionModel->decrementSeats((int) $booking['session_id'], (int) ($booking['nb_children'] ?? 1));

        Mailer::sendBookingConfirmationToAttendee($user, $session);
        Mailer::sendBookingNotificationToAdmin($user, $session);
    }

    flash('success', 'Votre pack a été réglé ! Toutes vos réservations sont confirmées. Vous recevrez des e-mails de confirmation.');
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}

// ── Basket checkout ───────────────────────────────────────────────────────────
if ($isBasket) {
    $bookingIds = $_SESSION['basket_checkout_booking_ids'] ?? [];
    unset($_SESSION['basket_checkout_booking_ids']);

    if (empty($bookingIds)) {
        // Already processed or session expired.
        flash('success', 'Vos réservations ont été confirmées !');
        header('Location: ' . APP_BASE_URL . '/my-sessions.php');
        exit;
    }

    $isDemo      = isset($_GET['_demo']);
    $bookingModel = new BookingModel();
    $sessionModel = new SessionModel();
    $userModel    = new UserModel();
    $user         = $userModel->findById(Auth::currentUserId());
    $basketModel  = new BasketModel();

    $paymentRef = $isDemo
        ? 'demo_basket_' . implode('_', $bookingIds)
        : ($_GET['payment_intent'] ?? $_GET['referenceId'] ?? 'paid_basket_' . implode('_', $bookingIds));

    foreach ($bookingIds as $bookingId) {
        $booking = $bookingModel->findById((int) $bookingId);
        if (!$booking || $booking['status'] !== 'pending') {
            continue;
        }

        $bookingModel->confirm((int) $bookingId, $paymentRef);
        $session = $sessionModel->findById((int) $booking['session_id']);
        $sessionModel->decrementSeats((int) $booking['session_id'], (int) ($booking['nb_children'] ?? 1));

        Mailer::sendBookingConfirmationToAttendee($user, $session);
        Mailer::sendBookingNotificationToAdmin($user, $session);
    }

    // Clear the basket now that all bookings are confirmed
    $basketModel->clearByUser(Auth::currentUserId());

    flash('success', 'Votre panier a été réglé ! Vos réservations sont confirmées. Vous recevrez des e-mails de confirmation.');
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}

// ── Single booking checkout ───────────────────────────────────────────────────
$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
$isDemo    = isset($_GET['_demo']); // true in dev when Stripe is not configured

$bookingModel = new BookingModel();
$booking = $bookingModel->findById($bookingId);

if (!$booking || (int) $booking['user_id'] !== Auth::currentUserId()) {
    flash('error', 'Réservation introuvable.');
    header('Location: ' . APP_BASE_URL . '/');
    exit;
}

if ($booking['status'] === 'pending') {
    // In production: validate via webhook (Stripe: payment_intent, Square: order.fulfillment.updated).
    // Here we confirm immediately, relying on the provider's hosted page having completed payment.
    // If a Square order ID was pre-stored (set in book.php before redirecting), preserve it.
    $preStoredRef = (string) ($booking['payment_intent_id'] ?? '');
    $paymentRef = $isDemo
        ? 'demo_' . $bookingId
        : ($preStoredRef !== ''
            ? $preStoredRef
            : ($_GET['payment_intent'] ?? $_GET['referenceId'] ?? 'paid_' . $bookingId));
    $bookingModel->confirm($bookingId, $paymentRef);

    $sessionModel = new SessionModel();
    $session = $sessionModel->findById((int) $booking['session_id']);
    $sessionModel->decrementSeats((int) $booking['session_id'], (int) ($booking['nb_children'] ?? 1));

    $userModel = new UserModel();
    $user = $userModel->findById(Auth::currentUserId());

    Mailer::sendBookingConfirmationToAttendee($user, $session);
    Mailer::sendBookingNotificationToAdmin($user, $session);
}

flash('success', 'Votre réservation est confirmée ! Vous recevrez un e-mail de confirmation.');
header('Location: ' . APP_BASE_URL . '/my-sessions.php');
exit;

