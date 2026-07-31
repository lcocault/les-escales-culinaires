<?php
// public/admin/shop-product-delete.php – soft-delete a shop product
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/admin/shop-products.php');
    exit;
}

Auth::verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);
$productModel = new ShopProductModel();
$product = $productModel->findById($id);

if (!$product) {
    flash('error', 'Produit introuvable.');
    header('Location: ' . APP_BASE_URL . '/admin/shop-products.php');
    exit;
}

$productModel->delete($id);

flash('success', 'Produit supprimé.');
header('Location: ' . APP_BASE_URL . '/admin/shop-products.php');
exit;
