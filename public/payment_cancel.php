<?php
// public/payment_cancel.php – user cancelled on the payment provider's hosted page
require_once __DIR__ . '/init.php';

Auth::start();
$isGroupBooking = isset($_GET['group_booking_id']);
$isBasket = isset($_GET['basket']);
$isPack   = isset($_GET['pack']);

if ($isGroupBooking) {
    if (!Auth::isLoggedIn()) {
        flash('info', 'Reconnectez-vous pour reprendre le règlement de votre séance anniversaire.');
        header('Location: ' . APP_BASE_URL . '/login.php');
        exit;
    }

    flash('info', 'Le paiement a été annulé. Votre séance anniversaire n\'est pas encore confirmée.');
    header('Location: ' . APP_BASE_URL . '/my-group-bookings.php');
    exit;
}

if ($isPack && Auth::isLoggedIn()) {
    // Clean up pending bookings created for the pack checkout
    $bookingIds = $_SESSION['pack_checkout_booking_ids'] ?? [];
    $packId     = $_SESSION['pack_checkout_pack_id'] ?? 0;
    unset($_SESSION['pack_checkout_booking_ids'], $_SESSION['pack_checkout_pack_id']);

    if (!empty($bookingIds)) {
        $bookingModel = new BookingModel();
        foreach ($bookingIds as $bid) {
            $booking = $bookingModel->findById((int) $bid);
            if ($booking && $booking['status'] === 'pending') {
                $bookingModel->deleteById((int) $bid);
            }
        }
    }

    flash('info', 'Le paiement a été annulé. Les séances du pack n\'ont pas été réservées.');
    header('Location: ' . APP_BASE_URL . '/pack.php?id=' . (int) $packId);
    exit;
}

if ($isBasket && Auth::isLoggedIn()) {
    // Clean up pending bookings created for the basket checkout
    $bookingIds = $_SESSION['basket_checkout_booking_ids'] ?? [];
    unset($_SESSION['basket_checkout_booking_ids']);

    if (!empty($bookingIds)) {
        $bookingModel = new BookingModel();
        foreach ($bookingIds as $bid) {
            $booking = $bookingModel->findById((int) $bid);
            if ($booking && $booking['status'] === 'pending') {
                $bookingModel->deleteById((int) $bid);
            }
        }
    }

    flash('info', 'Le paiement a été annulé. Les séances de votre panier n\'ont pas été réservées.');
    header('Location: ' . APP_BASE_URL . '/basket.php');
    exit;
}

flash('info', 'Le paiement a été annulé. Votre réservation n\'a pas été confirmée.');
header('Location: ' . APP_BASE_URL . '/my-sessions.php');
exit;
