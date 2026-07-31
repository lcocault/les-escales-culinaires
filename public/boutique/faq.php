<?php
// public/boutique/faq.php – FAQ for the online shop
require_once __DIR__ . '/../init.php';

$pageTitle = 'Boutique – Questions fréquentes';
$navContext = 'shop';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>❓ Questions fréquentes – Boutique</h1>
        <p>Tout ce que vous souhaitez savoir sur notre boutique en ligne de plats préparés.</p>
    </section>

    <div class="faq-list">

        <details class="faq-item">
            <summary class="faq-item__question">Quels produits propose la boutique ?</summary>
            <div class="faq-item__answer">
                <p>
                    La boutique propose une sélection de <strong>plats préparés maison</strong> réalisés par
                    Emmanuelle Du Puy De Goyne à partir d'ingrédients frais et locaux. Le catalogue est mis
                    à jour régulièrement selon les saisons et les disponibilités.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Comment puis-je passer une commande ?</summary>
            <div class="faq-item__answer">
                <ol>
                    <li>Parcourez le <a href="<?= APP_BASE_URL ?>/boutique/">catalogue</a> et ajoutez vos plats au panier.</li>
                    <li>Accédez au panier et cliquez sur « Valider la commande ».</li>
                    <li>Choisissez votre mode de retrait et la date souhaitée, puis payez en ligne.</li>
                    <li>Vous recevrez une confirmation et récupérerez vos plats à la date convenue.</li>
                </ol>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Quels sont les modes de retrait disponibles ?</summary>
            <div class="faq-item__answer">
                <ul>
                    <li>🏪 <strong>Retrait en boutique</strong> – 36 rue Boieldieu, 31300 Toulouse (gratuit)</li>
                    <li>🥦 <strong>Marché Croix-de-Pierre</strong> – chaque mercredi et vendredi matin (gratuit)</li>
                    <li>🚚 <strong>Livraison à domicile</strong> – sur Toulouse et agglomération (5 €)</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Quand dois-je passer ma commande ?</summary>
            <div class="faq-item__answer">
                <p>
                    Les commandes doivent être passées <strong>au moins 2 jours avant</strong> la date de retrait
                    ou de livraison souhaitée. Cette contrainte permet de préparer vos plats dans les meilleures
                    conditions. Le site vous indiquera automatiquement la première date disponible lors de la
                    validation de votre commande.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Comment fonctionne la livraison à domicile ?</summary>
            <div class="faq-item__answer">
                <p>
                    La livraison à domicile est proposée sur Toulouse et l'agglomération toulousaine pour
                    <strong>5 €</strong>. Lors de la commande, vous indiquez votre adresse complète et choisissez
                    la date de livraison (au moins 2 jours à l'avance). Nous prendrons contact avec vous si besoin
                    pour préciser le créneau.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Puis-je annuler ou modifier ma commande ?</summary>
            <div class="faq-item__answer">
                <p>
                    Une fois la commande payée, elle est transmise en préparation. Si vous souhaitez l'annuler,
                    contactez-nous le plus tôt possible par téléphone ou e-mail. En cas d'annulation acceptée,
                    vous serez intégralement remboursé(e).
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Les prix sont-ils TTC ?</summary>
            <div class="faq-item__answer">
                <p>
                    Oui, tous les prix affichés sur le site sont en euros <strong>TTC</strong>
                    (art.&nbsp;293&nbsp;B du CGI). Les Escales Culinaires relèvent du régime des
                    auto-entrepreneurs.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Comment nous contacter ?</summary>
            <div class="faq-item__answer">
                <p>
                    📞 <a href="tel:+33650071091">06 50 07 10 91</a><br>
                    ✉️ <a href="mailto:les.escales.culinaires@gmail.com">les.escales.culinaires@gmail.com</a><br>
                    <a href="https://www.instagram.com/les.escales.culinaires" target="_blank" rel="noopener noreferrer" class="instagram-link">
                        <?php include ROOT_DIR . '/templates/instagram-icon.php'; ?>
                        @les.escales.culinaires
                    </a>
                </p>
                <p>
                    Responsable : <strong>Emmanuelle Du Puy De Goyne</strong>
                </p>
            </div>
        </details>

    </div>

    <div class="about-cta mt-3">
        <h2>Une autre question ?</h2>
        <p>Contactez-nous, nous serons ravis de vous répondre !</p>
        <div class="about-cta__actions">
            <a href="mailto:les.escales.culinaires@gmail.com" class="btn btn--primary btn--lg">✉️ Nous écrire</a>
            <a href="<?= APP_BASE_URL ?>/boutique/" class="btn btn--secondary btn--lg">🛍️ Voir le catalogue</a>
        </div>
    </div>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>

