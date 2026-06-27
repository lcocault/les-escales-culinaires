<?php
/**
 * bin/cron-check-sessions.php
 *
 * Cron job – session confirmation / cancellation check.
 *
 * Run this script once a day (e.g. 24 h before typical session start times).
 * It checks every pending session whose start datetime is within the next 24 hours
 * and either confirms it (enough participants) or cancels it (too few participants),
 * then sends the appropriate email to every confirmed attendee.
 *
 * Minimum participants threshold: SESSION_MIN_ATTENDEES constant (default: 2).
 *
 * Example crontab entry (runs daily at 08:00):
 *   0 8 * * * php /path/to/www/bin/cron-check-sessions.php >> /var/log/escales-cron.log 2>&1
 */

// Safety guard – CLI only.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied.' . PHP_EOL);
}

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/SessionModel.php';
require_once ROOT_DIR . '/src/BookingModel.php';
require_once ROOT_DIR . '/src/Mailer.php';
require_once ROOT_DIR . '/src/PaymentService.php';

$minAttendees = defined('SESSION_MIN_ATTENDEES') ? (int) SESSION_MIN_ATTENDEES : 2;

$sessionModel = new SessionModel();
$bookingModel = new BookingModel();

$sessions = $sessionModel->getSessionsDueForCheck();

if (empty($sessions)) {
    echo '[' . date('Y-m-d H:i:s') . '] No pending sessions due for check.' . PHP_EOL;
    exit(0);
}

foreach ($sessions as $session) {
    $sessionId    = (int) $session['id'];
    $sessionTitle = $session['title'];
    $confirmedBookings = $bookingModel->getConfirmedBySession($sessionId);
    $count = array_sum(array_column($confirmedBookings, 'number_of_children')) ?: count($confirmedBookings);

    echo '[' . date('Y-m-d H:i:s') . "] Session #{$sessionId} \"{$sessionTitle}\":"
        . " {$count} confirmed booking(s), minimum is {$minAttendees}." . PHP_EOL;

    if ($count >= $minAttendees) {
        // Confirm the session and notify all confirmed attendees.
        $sessionModel->confirmSession($sessionId);
        echo '[' . date('Y-m-d H:i:s') . "] Session #{$sessionId} CONFIRMED." . PHP_EOL;

        foreach ($confirmedBookings as $booking) {
            $user = [
                'first_name' => $booking['first_name'],
                'last_name'  => $booking['last_name'],
                'email'      => $booking['email'],
            ];
            Mailer::sendSessionConfirmationToAttendee($user, $session);
            echo '[' . date('Y-m-d H:i:s') . "]   Confirmation email sent to {$booking['email']}." . PHP_EOL;
        }
    } else {
        // Cancel the session, refund participants, and notify them.
        $sessionModel->cancelSession($sessionId);
        echo '[' . date('Y-m-d H:i:s') . "] Session #{$sessionId} CANCELLED (not enough participants)." . PHP_EOL;

        foreach ($confirmedBookings as $booking) {
            $bookingId       = (int) $booking['id'];
            $paymentIntentId = $booking['payment_intent_id'] ?? '';

            // Issue refund if a real payment was made.
            if (PaymentService::isRealPaymentRef($paymentIntentId)) {
                try {
                    PaymentService::refund($paymentIntentId);
                    echo '[' . date('Y-m-d H:i:s') . "]   Refund issued for booking #{$bookingId}." . PHP_EOL;
                } catch (\Exception $e) {
                    echo '[' . date('Y-m-d H:i:s') . "]   ERROR refunding booking #{$bookingId}: {$e->getMessage()}" . PHP_EOL;
                }
            }

            // Cancel the booking.
            $bookingModel->cancel($bookingId);

            // Restore the seat(s).
            $sessionModel->incrementSeats($sessionId, (int) ($booking['number_of_children'] ?? 1));

            // Notify the attendee.
            $user = [
                'first_name' => $booking['first_name'],
                'last_name'  => $booking['last_name'],
                'email'      => $booking['email'],
            ];
            Mailer::sendSessionCancellationToAttendee($user, $session);
            echo '[' . date('Y-m-d H:i:s') . "]   Cancellation email sent to {$booking['email']}." . PHP_EOL;
        }
    }
}

echo '[' . date('Y-m-d H:i:s') . '] Done.' . PHP_EOL;
