<?php
// public/admin/rating-reminder-action.php – send reminder or dismiss a booking from the rating reminder list
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/admin/rating-reminders.php');
    exit;
}

Auth::verifyCsrf();

$bookingId = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$action    = $_POST['action'] ?? '';

$bookingModel = new BookingModel();
$booking = $bookingModel->findById($bookingId);

if (!$booking) {
    flash('error', 'Réservation introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/rating-reminders.php');
    exit;
}

switch ($action) {
    case 'remind':
        $sessionModel = new SessionModel();
        $session = $sessionModel->findById((int) $booking['session_id']);
        $userModel = new UserModel();
        $user = $userModel->findById((int) $booking['user_id']);
        if ($session && $user) {
            Mailer::sendRatingReminder($user, $session);
            flash('success', 'Rappel envoyé à ' . $user['first_name'] . ' ' . $user['last_name'] . '.');
        } else {
            flash('error', 'Impossible de trouver la séance ou le participant.');
        }
        break;

    case 'dismiss':
        $bookingModel->dismissRatingReminder($bookingId);
        flash('info', 'Participation masquée de la liste.');
        break;

    default:
        flash('error', 'Action invalide.');
}

header('Location: ' . APP_BASE_URL . '/admin/rating-reminders.php');
exit;
