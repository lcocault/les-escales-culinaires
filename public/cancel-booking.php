<?php
// public/cancel-booking.php – cancel a confirmed booking with refund (up to 48 h before the session)
require_once __DIR__ . '/init.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}

Auth::verifyCsrf();

$bookingId    = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$bookingModel = new BookingModel();
$booking      = $bookingModel->findById($bookingId);

// Booking must exist and belong to the current user.
if (!$booking || (int) $booking['user_id'] !== Auth::currentUserId()) {
    flash('error', 'Réservation introuvable.');
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}

// Only confirmed bookings may be cancelled by the user.
if ($booking['status'] !== 'confirmed') {
    flash('error', 'Cette réservation ne peut pas être annulée.');
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}

// Enforce the 48-hour cancellation deadline.
$sessionModel = new SessionModel();
$session      = $sessionModel->findById((int) $booking['session_id']);

if (!$session) {
    flash('error', 'Séance introuvable.');
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}

$sessionStart = strtotime($session['session_date'] . ' ' . $session['start_time']);
if ($sessionStart - time() < 48 * 3600) {
    flash('error', 'L\'annulation n\'est plus possible moins de 48 heures avant la séance.');
    header('Location: ' . APP_BASE_URL . '/my-sessions.php');
    exit;
}

// Refund payment or restore credit.
$userModel = new UserModel();
$user      = $userModel->findById(Auth::currentUserId());

if ($booking['used_credit']) {
    // Booking was paid with a credit – give it back.
    $userModel->updateCredits(Auth::currentUserId(), 1);
} elseif (PaymentService::isRealPaymentRef((string) ($booking['payment_intent_id'] ?? ''))) {
    // Real payment intent – issue a refund via the payment provider.
    try {
        PaymentService::refund($booking['payment_intent_id']);
    } catch (RuntimeException $e) {
        error_log('Refund error for booking #' . $bookingId . ': ' . $e->getMessage());
        flash('error', 'Une erreur est survenue lors du remboursement. Veuillez contacter l\'administrateur.');
        header('Location: ' . APP_BASE_URL . '/my-sessions.php');
        exit;
    }
}

// Restore the seat and delete the booking record.
$sessionModel->incrementSeats((int) $booking['session_id'], (int) ($booking['nb_children'] ?? 1));
$bookingModel->deleteById($bookingId);

// Notify the attendee and the admin.
Mailer::sendCancellationConfirmationToAttendee($user, $session);
Mailer::sendCancellationNotificationToAdmin($user, $session);

flash('success', 'Votre réservation a été annulée. Vous recevrez un remboursement sous quelques jours ouvrés.');
header('Location: ' . APP_BASE_URL . '/my-sessions.php');
exit;
