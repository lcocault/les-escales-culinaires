<?php
// public/admin/shop-products.php – manage shop product catalog
require_once __DIR__ . '/../init.php';
Auth::requireAdmin();

$productModel = new ShopProductModel();
$products = $productModel->getAll();

$pageTitle = 'Boutique – Catalogue';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:.5rem">
        <h1 class="page-title" style="margin:0">🛍️ Catalogue boutique</h1>
        <a href="<?= APP_BASE_URL ?>/admin/shop-product-edit.php" class="btn btn--primary">+ Nouveau produit</a>
    </div>

    <?php if (empty($products)): ?>
        <p>Aucun produit. <a href="<?= APP_BASE_URL ?>/admin/shop-product-edit.php">Créer le premier produit</a>.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Nom</th>
                        <th>Prix</th>
                        <th>Portions</th>
                        <th>Min. commande</th>
                        <th>Disponibilité</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php $productImgSrc = shopProductImageSrc($p); ?>
                        <tr>
                            <td>
                                <?php if ($productImgSrc !== null): ?>
                                    <img src="<?= e($productImgSrc) ?>"
                                         alt="<?= e($p['name']) ?>"
                                         style="width:60px;height:60px;object-fit:cover;border-radius:6px">
                                <?php else: ?>
                                    <span style="color:var(--color-muted)">–</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['name']) ?></td>
                            <td><?= formatPrice((int) $p['price_cents']) ?></td>
                            <td><?= max(1, (int) ($p['portion_count'] ?? 1)) ?></td>
                            <td><?= max(1, (int) ($p['min_order_portions'] ?? 1)) ?></td>
                            <td>
                                <?php if ($p['is_available'] === true || $p['is_available'] === 't'): ?>
                                    <span class="badge" style="background:#16a34a;color:#fff">✅ Disponible</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#6b7280;color:#fff">❌ Indisponible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="<?= APP_BASE_URL ?>/admin/shop-product-edit.php?id=<?= (int) $p['id'] ?>"
                                       class="btn btn--warning btn--icon" title="Modifier" aria-label="Modifier">✏️</a>
                                    <form method="post" action="<?= APP_BASE_URL ?>/admin/shop-product-delete.php"
                                          onsubmit="return confirm('Supprimer ce produit ?')">
                                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn btn--danger btn--icon" title="Supprimer" aria-label="Supprimer">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="mt-3"><a href="<?= APP_BASE_URL ?>/admin/">← Retour au tableau de bord</a></p>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
