<?php
// public/admin/group-session-slot-delete.php – soft-delete a group session slot
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/admin/group-session-slots.php');
    exit;
}

Auth::verifyCsrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$slotModel = new GroupSessionSlotModel();
$slot      = $slotModel->findById($id);

if (!$slot) {
    flash('error', 'Créneau introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/group-session-slots.php');
    exit;
}

$slotModel->softDelete($id);

flash('success', 'Créneau supprimé avec succès.');
header('Location: ' . APP_BASE_URL . '/admin/group-session-slots.php');
exit;
