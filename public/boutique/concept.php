<?php
// public/boutique/concept.php – concept page for the online shop
require_once __DIR__ . '/../init.php';

$pageTitle = 'La boutique – Le concept';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>🛍️ La Boutique Escales Culinaires</h1>
        <p>Des plats préparés maison à commander et récupérer près de chez vous !</p>
    </section>

    <!-- Pitch principal -->
    <section class="about-section about-section--highlight">
        <div class="about-section__icon">🍽️</div>
        <div class="about-section__content">
            <h2>Nos plats préparés maison</h2>
            <p>
                Emmanuelle Du Puy De Goyne, créatrice des Escales Culinaires, met son savoir-faire
                culinaire à votre service en proposant une sélection de plats préparés maison.
                Chaque recette est élaborée avec soin, à partir de produits frais et locaux, pour
                vous régaler à la maison sans effort.
            </p>
        </div>
    </section>

    <!-- Comment commander -->
    <section class="about-section">
        <div class="about-section__icon">🛒</div>
        <div class="about-section__content">
            <h2>Comment commander ?</h2>
            <ol class="about-list" style="list-style:none;padding:0">
                <li>
                    <span class="about-list__icon">1️⃣</span>
                    <span><strong>Choisissez vos plats</strong> dans notre catalogue et ajoutez-les à votre panier.</span>
                </li>
                <li>
                    <span class="about-list__icon">2️⃣</span>
                    <span><strong>Sélectionnez votre mode de retrait</strong> : marché, boutique ou livraison à domicile.</span>
                </li>
                <li>
                    <span class="about-list__icon">3️⃣</span>
                    <span><strong>Payez en ligne</strong> de façon sécurisée.</span>
                </li>
                <li>
                    <span class="about-list__icon">4️⃣</span>
                    <span><strong>Récupérez vos plats</strong> à la date choisie, frais et prêts à déguster !</span>
                </li>
            </ol>
        </div>
    </section>

    <!-- Modes de retrait -->
    <section class="about-section about-section--highlight">
        <div class="about-section__icon">📍</div>
        <div class="about-section__content">
            <h2>Modes de retrait / livraison</h2>
            <ul class="about-list">
                <li>
                    <span class="about-list__icon">🏪</span>
                    <span><strong>Retrait en boutique</strong> – 36 rue Boieldieu, 31300 Toulouse (gratuit)</span>
                </li>
                <li>
                    <span class="about-list__icon">🥦</span>
                    <span><strong>Marché Croix-de-Pierre</strong> – chaque mercredi et vendredi matin (gratuit)</span>
                </li>
                <li>
                    <span class="about-list__icon">🚚</span>
                    <span><strong>Livraison à domicile</strong> – sur Toulouse et agglomération (5 €)</span>
                </li>
            </ul>
            <p class="mt-1" style="font-size:.9rem;color:var(--color-muted)">
                ⏰ Les commandes doivent être passées au moins 2 jours avant la date de retrait / livraison souhaitée.
            </p>
        </div>
    </section>

    <!-- Nos engagements -->
    <section class="about-section">
        <div class="about-section__icon">💛</div>
        <div class="about-section__content">
            <h2>Nos engagements</h2>
            <ul class="about-list">
                <li>
                    <span class="about-list__icon">🌱</span>
                    <span><strong>Produits frais</strong> : des ingrédients de qualité, sourcés localement autant que possible.</span>
                </li>
                <li>
                    <span class="about-list__icon">👩‍🍳</span>
                    <span><strong>Fait maison</strong> : chaque plat est préparé à la main avec amour.</span>
                </li>
                <li>
                    <span class="about-list__icon">❤️</span>
                    <span><strong>Passion</strong> : la même passion culinaire qui anime nos ateliers pour enfants.</span>
                </li>
                <li>
                    <span class="about-list__icon">🤝</span>
                    <span><strong>Proximité</strong> : une boutique locale, à Toulouse, avec un service personnalisé.</span>
                </li>
            </ul>
        </div>
    </section>

    <!-- Où nous trouver -->
    <section class="about-section about-section--highlight">
        <div class="about-section__icon">📞</div>
        <div class="about-section__content">
            <h2>Nous contacter</h2>
            <address class="about-address">
                <strong>Les Escales Culinaires</strong><br>
                36 rue Boieldieu<br>
                31300 Toulouse<br>
                France
            </address>
            <p class="mt-1">
                📞 <a href="tel:+33650071091">06 50 07 10 91</a><br>
                ✉️ <a href="mailto:les.escales.culinaires@gmail.com">les.escales.culinaires@gmail.com</a>
            </p>
            <p class="mt-1 about-owner">
                Les Escales Culinaires sont animées par <strong>Emmanuelle Du Puy De Goyne</strong>,
                auto-entrepreneur. Les prix indiqués sur le site sont en euros TTC
                (art.&nbsp;293&nbsp;B du CGI).
            </p>
        </div>
    </section>

    <!-- CTA -->
    <section class="about-cta">
        <h2>Prêt(e) à commander ?</h2>
        <p>Découvrez notre sélection de plats préparés et passez votre commande en quelques clics !</p>
        <div class="about-cta__actions">
            <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--primary btn--lg">🛍️ Voir le catalogue</a>
            <a href="<?= APP_BASE_URL ?>/boutique/faq.php" class="btn btn--secondary btn--lg">❓ Questions fréquentes</a>
        </div>
    </section>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
