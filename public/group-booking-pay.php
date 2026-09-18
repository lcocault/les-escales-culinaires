<?php
// public/group-booking-pay.php – start payment for an admin-approved private/group session
require_once __DIR__ . '/init.php';
Auth::requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$model = new GroupBookingModel();
$request = $model->findById($id);

if (!$request || (int) $request['user_id'] !== Auth::currentUserId()) {
    flash('error', 'Demande introuvable.');
    header('Location: ' . APP_BASE_URL . '/my-group-bookings.php');
    exit;
}

if ($request['status'] === 'confirmed') {
    flash('info', 'Cette séance anniversaire est déjà réglée et confirmée.');
    header('Location: ' . APP_BASE_URL . '/my-group-bookings.php');
    exit;
}

if ($request['status'] !== 'awaiting_payment') {
    flash('error', 'Le paiement n\'est pas encore disponible pour cette demande.');
    header('Location: ' . APP_BASE_URL . '/my-group-bookings.php');
    exit;
}

$amountCents = GroupBookingModel::estimatePriceFromRequest($request);
$itemName = 'Atelier anniversaire du ' . date('d/m/Y', strtotime($request['preferred_date']));

try {
    $checkout = PaymentService::createGroupBookingCheckoutUrl($id, $itemName, $amountCents, 'eur');

    if (!empty($checkout['squareOrderId'])) {
        $model->storePaymentReference($id, 'sq_order_' . $checkout['squareOrderId']);
    }

    header('Location: ' . $checkout['url']);
    exit;
} catch (RuntimeException $e) {
    error_log('Group booking payment error for request #' . $id . ': ' . $e->getMessage());
    flash('error', 'Impossible de générer le lien de paiement pour le moment. Merci de réessayer plus tard.');
    header('Location: ' . APP_BASE_URL . '/my-group-bookings.php');
    exit;
}
