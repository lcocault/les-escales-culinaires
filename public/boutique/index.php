<?php
// public/boutique/index.php – shop catalog
require_once __DIR__ . '/../init.php';

$productModel = new ShopProductModel();
$products = $productModel->getAvailable();

$pageTitle = 'Boutique – Catalogue';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>🛍️ Boutique – Plats préparés</h1>
        <p>Commandez nos plats préparés maison et récupérez-les au marché, à la boutique ou faites-vous livrer !</p>
    </section>

    <?php if (empty($products)): ?>
        <p style="color:var(--color-muted);text-align:center;margin-top:2rem">
            Aucun produit disponible pour le moment. Revenez bientôt !
        </p>
    <?php else: ?>
        <div class="sessions-grid">
            <?php foreach ($products as $p): ?>
                <div class="session-card">
                    <?php
                    $productImgSrc = shopProductImageSrc($p);
                    ?>
                    <?php if ($productImgSrc !== null): ?>
                        <img src="<?= e($productImgSrc) ?>"
                             alt="<?= e($p['name']) ?>"
                             class="session-card__img"
                             style="width:100%;height:200px;object-fit:cover;border-radius:var(--radius) var(--radius) 0 0;display:block">
                    <?php else: ?>
                        <div style="width:100%;height:140px;background:var(--color-bg-alt);border-radius:var(--radius) var(--radius) 0 0;display:flex;align-items:center;justify-content:center;font-size:3rem">🍽️</div>
                    <?php endif; ?>

                    <div class="session-card__body">
                        <h2 class="session-card__title"><?= e($p['name']) ?></h2>
                        <?php if (!empty($p['description'])): ?>
                            <p class="session-card__meta"><?= e($p['description']) ?></p>
                        <?php endif; ?>
                        <p class="session-card__price" style="font-size:1.2rem;font-weight:bold;color:var(--color-primary);margin:.5rem 0">
                            <?= formatPrice((int) $p['price_cents']) ?>
                        </p>
                        <?php $portionCount = max(1, (int) ($p['portion_count'] ?? 1)); ?>
                        <?php $minOrderPortions = max(1, (int) ($p['min_order_portions'] ?? 1)); ?>
                        <p class="session-card__meta">🍽️ <?= $portionCount ?> portion<?= $portionCount > 1 ? 's' : '' ?></p>
                        <p class="session-card__meta">📦 Minimum commande : <?= $minOrderPortions ?> portion<?= $minOrderPortions > 1 ? 's' : '' ?></p>

                        <form method="post" action="<?= APP_BASE_URL ?>/boutique/cart.php">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="redirect_to" value="/boutique/">
                            <div style="display:flex;align-items:center;gap:.5rem;margin-top:.75rem">
                                <label for="qty_<?= (int) $p['id'] ?>" class="sr-only">Quantité</label>
                                <input type="number" id="qty_<?= (int) $p['id'] ?>" name="quantity"
                                       min="<?= $minOrderPortions ?>" max="20" value="<?= $minOrderPortions ?>"
                                       style="width:60px;padding:.3rem .5rem;border:1px solid var(--color-border);border-radius:var(--radius)">
                                <button type="submit" class="btn btn--primary">🛒 Ajouter</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (shopCartCount() > 0): ?>
            <div style="text-align:center;margin-top:2rem">
                <a href="<?= APP_BASE_URL ?>/boutique/cart.php" class="btn btn--primary btn--lg">
                    🛒 Voir mon panier (<?= shopCartCount() ?> article<?= shopCartCount() > 1 ? 's' : '' ?>)
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
