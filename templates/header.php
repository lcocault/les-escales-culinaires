<?php
// templates/header.php
// $pageTitle should be set before including this file
// $navContext controls which nav links are shown:
//   'home'     – auth links only (Connexion / S'inscrire)
//   'sessions' – Séances + concept(/ateliers/concept.php) + FAQ(/ateliers/faq.php) + Avis + auth links
//   'shop'     – Catalogue + concept(/boutique/concept.php) + FAQ(/boutique/faq.php) + auth links with shop cart (no Séances)
//   null       – full nav (all links, default behaviour)
$pageTitle  = $pageTitle ?? 'Escales Culinaires';
$navContext = $navContext ?? null;
Auth::start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> – Escales Culinaires</title>
    <link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="site-header__inner container">
        <a class="site-header__logo" href="<?= APP_BASE_URL ?>/">
            <img src="<?= APP_BASE_URL ?>/img/header.png" alt="Les Escales Culinaires" class="site-logo" onerror="this.style.display='none'">
            <span class="logo-text">Escales Culinaires</span>
        </a>
        <nav class="site-nav" aria-label="Navigation principale">
            <ul class="site-nav__list">
                <?php if ($navContext !== 'home'): ?>
                    <?php if ($navContext !== 'shop'): ?>
                        <li><a href="<?= APP_BASE_URL ?>/ateliers/">Séances</a></li>
                        <li><a href="<?= APP_BASE_URL ?>/ateliers/concept.php">Le concept</a></li>
                        <li><a href="<?= APP_BASE_URL ?>/ateliers/faq.php">FAQ</a></li>
                    <?php else: ?>
                        <li><a href="<?= APP_BASE_URL ?>/boutique/">Catalogue</a></li>
                        <li><a href="<?= APP_BASE_URL ?>/boutique/concept.php">Le concept</a></li>
                        <li><a href="<?= APP_BASE_URL ?>/boutique/faq.php">FAQ</a></li>
                    <?php endif; ?>
                    <li><a href="<?= APP_BASE_URL ?>/all-ratings.php">⭐ Avis</a></li>
                <?php endif; ?>
                <?php if (Auth::isLoggedIn()): ?>
                    <?php if ($navContext === 'shop'): ?>
                        <?php $shopCartCount = shopCartCount(); ?>
                        <li>
                            <a href="<?= APP_BASE_URL ?>/boutique/cart.php" class="basket-nav-link">
                                🛒 Panier<?php if ($shopCartCount > 0): ?>
                                    <span class="basket-badge"><?= $shopCartCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="<?= APP_BASE_URL ?>/boutique/my-orders.php">Mes commandes</a></li>
                    <?php else: ?>
                        <?php $basketCount = currentBasketCount(); ?>
                        <li>
                            <a href="<?= APP_BASE_URL ?>/basket.php" class="basket-nav-link">
                                🛒 Panier<?php if ($basketCount > 0): ?>
                                    <span class="basket-badge"><?= $basketCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="<?= APP_BASE_URL ?>/my-sessions.php">Mes réservations</a></li>
                    <?php endif; ?>
                    <?php if (Auth::isAdmin()): ?>
                        <li><a href="<?= APP_BASE_URL ?>/admin/">Administration</a></li>
                    <?php endif; ?>
                    <li><a href="<?= APP_BASE_URL ?>/profile.php">Mon compte</a></li>
                    <li>
                        <form method="post" action="<?= APP_BASE_URL ?>/logout.php" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <button type="submit" class="btn btn--link">Déconnexion</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="<?= APP_BASE_URL ?>/login.php">Connexion</a></li>
                    <li><a href="<?= APP_BASE_URL ?>/register.php" class="btn btn--primary">S'inscrire</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<main class="site-main">
