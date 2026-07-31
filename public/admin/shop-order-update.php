<?php
// public/admin/shop-order-update.php – update a shop order status (prepared / delivered / cancel)
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/admin/shop-orders.php');
    exit;
}

Auth::verifyCsrf();

$id     = (int) ($_POST['id']     ?? 0);
$action = trim($_POST['action']   ?? '');
$redirect = trim($_POST['redirect'] ?? '');

$orderModel = new ShopOrderModel();
$order = $orderModel->findById($id);

if (!$order) {
    flash('error', 'Commande introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/shop-orders.php');
    exit;
}

$backUrl = ($redirect === 'detail')
    ? APP_BASE_URL . '/admin/shop-order-detail.php?id=' . $id
    : APP_BASE_URL . '/admin/shop-orders.php';

switch ($action) {
    case 'prepared':
        if ($order['status'] !== 'paid') {
            flash('error', 'Cette commande ne peut pas être marquée comme préparée.');
            header('Location: ' . $backUrl);
            exit;
        }
        $orderModel->markPrepared($id);
        flash('success', 'Commande marquée comme préparée.');
        break;

    case 'delivered':
        if ($order['status'] !== 'prepared') {
            flash('error', 'Cette commande ne peut pas être marquée comme livrée.');
            header('Location: ' . $backUrl);
            exit;
        }
        $orderModel->markDelivered($id);
        flash('success', 'Commande marquée comme livrée / remise.');
        break;

    case 'cancel':
        if (!in_array($order['status'], ['paid', 'prepared'], true)) {
            flash('error', 'Cette commande ne peut pas être annulée.');
            header('Location: ' . $backUrl);
            exit;
        }
        $orderModel->cancel($id);

        // Issue refund if a real payment was made
        $paymentRef = $order['payment_intent_id'] ?? '';
        if (PaymentService::isRealPaymentRef($paymentRef)) {
            try {
                PaymentService::refund($paymentRef);
                flash('success', 'Commande annulée et remboursement effectué.');
            } catch (\Exception $e) {
                flash('error', 'Commande annulée mais le remboursement a échoué : ' . $e->getMessage());
            }
        } else {
            flash('success', 'Commande annulée.');
        }
        break;

    default:
        flash('error', 'Action non reconnue.');
}

header('Location: ' . $backUrl);
exit;
