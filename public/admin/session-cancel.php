<?php
// public/admin/session-cancel.php – cancel a session, refund all confirmed bookings, notify participants
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
    exit;
}

Auth::verifyCsrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    flash('error', 'Séance introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
    exit;
}

$sessionModel = new SessionModel();
$session      = $sessionModel->findById($id);

if (!$session) {
    flash('error', 'Séance introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
    exit;
}

if ($session['status'] === 'cancelled') {
    flash('error', 'Cette séance est déjà annulée.');
    header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
    exit;
}

// Cancel the session.
$sessionModel->cancelSession($id);

// Process all confirmed bookings: refund and notify.
$bookingModel      = new BookingModel();
$confirmedBookings = $bookingModel->getConfirmedBySession($id);

$refundErrors = 0;
foreach ($confirmedBookings as $booking) {
    $bookingId       = (int) $booking['id'];
    $paymentIntentId = (string) ($booking['payment_intent_id'] ?? '');

    // Issue refund if a real payment was made.
    if (PaymentService::isRealPaymentRef($paymentIntentId)) {
        try {
            PaymentService::refund($paymentIntentId);
        } catch (RuntimeException $e) {
            error_log('Refund error for booking #' . $bookingId . ': ' . $e->getMessage());
            $refundErrors++;
        }
    }

    // Cancel the booking and restore the seats (one per child registered).
    $bookingModel->cancel($bookingId);
    $sessionModel->incrementSeats($id, (int) ($booking['nb_children'] ?? 1));

    // Notify the attendee.
    $user = [
        'first_name' => $booking['first_name'],
        'last_name'  => $booking['last_name'],
        'email'      => $booking['email'],
    ];
    Mailer::sendSessionCancellationToAttendee($user, $session);
}

if ($refundErrors > 0) {
    flash(
        'warning',
        'Séance annulée, mais ' . $refundErrors . ' remboursement(s) ont échoué. Veuillez les traiter manuellement.'
    );
} else {
    $count = count($confirmedBookings);
    flash('success', 'Séance annulée. ' . $count . ' participant(s) remboursé(s) et notifié(s) par e-mail.');
}

header('Location: ' . APP_BASE_URL . '/admin/sessions.php');
exit;
