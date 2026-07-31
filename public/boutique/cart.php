<?php
// public/boutique/cart.php – shop cart (session-based)
require_once __DIR__ . '/../init.php';

Auth::start();

$productModel = new ShopProductModel();

// Handle add / remove / update actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action    = trim($_POST['action'] ?? '');
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity  = (int) ($_POST['quantity'] ?? 1);
    $redirectPath = trim((string) ($_POST['redirect_to'] ?? '/boutique/cart.php'));
    if ($redirectPath === '' || $redirectPath[0] !== '/') {
        $redirectPath = '/boutique/cart.php';
    }
    $allowedRedirectPaths = ['/boutique/', '/boutique/cart.php'];
    if (!in_array($redirectPath, $allowedRedirectPaths, true)) {
        $redirectPath = '/boutique/cart.php';
    }

    if (!isset($_SESSION['shop_cart'])) {
        $_SESSION['shop_cart'] = [];
    }

    switch ($action) {
        case 'add':
            $product = $productId > 0 ? $productModel->findById($productId) : null;
            if ($productId > 0 && $product !== null) {
                $minOrderPortions = max(1, (int) ($product['min_order_portions'] ?? 1));
                $quantity = max($minOrderPortions, $quantity);
                $_SESSION['shop_cart'][$productId] = (int) ($_SESSION['shop_cart'][$productId] ?? 0) + $quantity;
            }
            flash('success', 'Produit ajouté au panier !');
            break;
        case 'update':
            $product = $productId > 0 ? $productModel->findById($productId) : null;
            if ($productId > 0 && $product !== null && $quantity > 0) {
                $minOrderPortions = max(1, (int) ($product['min_order_portions'] ?? 1));
                $quantity = max($minOrderPortions, $quantity);
                $_SESSION['shop_cart'][$productId] = $quantity;
            }
            break;
        case 'remove':
            if ($productId > 0) {
                unset($_SESSION['shop_cart'][$productId]);
            }
            break;
    }

    // Redirect to avoid form re-submission
    header('Location: ' . APP_BASE_URL . $redirectPath);
    exit;
}

// Build cart details
$cart = $_SESSION['shop_cart'] ?? [];
$cartItems  = [];
$totalCents = 0;

if (!empty($cart)) {
    $productIds = array_keys($cart);
    $cartWasAdjustedToMinimum = false;
    // Fetch each product; remove from cart if no longer available
    foreach ($productIds as $pid) {
        $product = $productModel->findById((int) $pid);
        if ($product === null) {
            unset($_SESSION['shop_cart'][$pid]);
            continue;
        }
        $minOrderPortions = max(1, (int) ($product['min_order_portions'] ?? 1));
        $qty       = max($minOrderPortions, (int) $cart[$pid]);
        if ($qty !== (int) $cart[$pid]) {
            $_SESSION['shop_cart'][$pid] = $qty;
            $cartWasAdjustedToMinimum = true;
        }
        $subtotal  = (int) $product['price_cents'] * $qty;
        $totalCents += $subtotal;
        $cartItems[] = [
            'product'  => $product,
            'quantity' => $qty,
            'subtotal' => $subtotal,
            'min_order_portions' => $minOrderPortions,
        ];
    }

    if ($cartWasAdjustedToMinimum) {
        flash('info', 'Certaines quantités ont été ajustées pour respecter le minimum de portions par commande.');
    }
}

$pageTitle = 'Mon panier – Boutique';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <h1 class="page-title">🛒 Mon panier</h1>

    <?php if (empty($cartItems)): ?>
        <p style="color:var(--color-muted)">Votre panier est vide.</p>
        <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--primary" style="margin-top:1rem">← Voir le catalogue</a>
    <?php else: ?>
        <div class="table-wrapper" style="max-width:700px">
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Prix unit.</th>
                        <th>Qté</th>
                        <th>Sous-total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <?php $p = $item['product']; ?>
                        <?php $productImgSrc = shopProductImageSrc($p); ?>
                        <tr>
                            <td>
                                <?php if ($productImgSrc !== null): ?>
                                    <img src="<?= e($productImgSrc) ?>"
                                         alt="<?= e($p['name']) ?>"
                                         style="width:48px;height:48px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:.5rem">
                                <?php endif; ?>
                                <?= e($p['name']) ?>
                                <?php $portionCount = max(1, (int) ($p['portion_count'] ?? 1)); ?>
                                <div style="font-size:.85rem;color:var(--color-muted)">
                                    <?= $portionCount ?> portion<?= $portionCount > 1 ? 's' : '' ?>
                                </div>
                            </td>
                            <td><?= formatPrice((int) $p['price_cents']) ?></td>
                            <td>
                                <form method="post" action="" style="display:inline-flex;gap:.25rem;align-items:center">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                                    <input type="number" name="quantity" value="<?= $item['quantity'] ?>"
                                           min="<?= (int) $item['min_order_portions'] ?>" max="20"
                                           style="width:55px;padding:.25rem .4rem;border:1px solid var(--color-border);border-radius:var(--radius)"
                                           onchange="this.form.submit()">
                                </form>
                            </td>
                            <td><?= formatPrice($item['subtotal']) ?></td>
                            <td>
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn--danger btn--icon" title="Retirer"
                                            aria-label="Retirer">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:bold">
                        <td colspan="3" style="text-align:right">Total articles</td>
                        <td colspan="2"><?= formatPrice($totalCents) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="display:flex;gap:1rem;margin-top:1.5rem;flex-wrap:wrap">
            <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--secondary">← Continuer mes achats</a>
            <a href="<?= APP_BASE_URL ?>/boutique/checkout.php" class="btn btn--primary btn--lg">
                Valider la commande →
            </a>
        </div>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
