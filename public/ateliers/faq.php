<?php
// public/ateliers/faq.php – FAQ for the kids cooking workshops
require_once __DIR__ . '/../init.php';

$pageTitle = 'FAQ – Questions fréquentes';
$navContext = 'sessions';
include ROOT_DIR . '/templates/header.php';
?>
<div class="container">
    <?php include ROOT_DIR . '/templates/flash.php'; ?>

    <section class="hero">
        <h1>❓ Questions fréquentes</h1>
        <p>Tout ce que vous souhaitez savoir sur les Escales Culinaires.</p>
    </section>

    <div class="faq-list">

        <details class="faq-item">
            <summary class="faq-item__question">À qui s'adressent les ateliers ?</summary>
            <div class="faq-item__answer">
                <p>Les ateliers sont ouverts aux enfants et adolescents, répartis en quatre tranches d'âge :</p>
                <ul>
                    <li><strong>3 à 5 ans</strong> : activités simples et sensorielles pour les tout-petits.</li>
                    <li><strong>3 à 10 ans</strong> : ateliers mixtes accessibles à tous les enfants, du tout-petit au grand.</li>
                    <li><strong>6 à 12 ans</strong> : recettes accessibles pour développer l'autonomie et la créativité.</li>
                    <li><strong>13 ans et +</strong> : techniques plus élaborées pour les ados passionnés de cuisine.</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Où se déroulent les séances ?</summary>
            <div class="faq-item__answer">
                <p>Tous les ateliers ont lieu à l'adresse suivante :</p>
                <address class="about-address">
                    <strong>Les Escales Culinaires</strong><br>
                    36 rue Boieldieu<br>
                    31300 Toulouse<br>
                    France
                </address>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Comment réserver une place ?</summary>
            <div class="faq-item__answer">
                <p>
                    Créez un compte sur le site, consultez les séances disponibles et cliquez sur
                    « Réserver ». Le paiement en ligne sécurise immédiatement la place de votre enfant.
                    Vous recevrez ensuite un e-mail de confirmation.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Quels sont les tarifs ? Les prix sont-ils TTC ?</summary>
            <div class="faq-item__answer">
                <p>
                    Le prix de chaque séance est affiché sur sa fiche. Tous les prix sont indiqués en
                    euros <strong>TTC</strong> (art.&nbsp;293&nbsp;B du CGI).
                    Les Escales Culinaires relèvent du régime des auto-entrepreneurs.
                </p>
                <p>
                    Le tarif proposé est <strong>très compétitif</strong> : il couvre le temps de
                    l'animatrice, les ingrédients, l'énergie, le matériel (tablier inclus),
                    le ménage et les taxes.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Que faut-il prévoir pour la séance ?</summary>
            <div class="faq-item__answer">
                <ul>
                    <li>Votre enfant peut apporter <strong>son propre tablier</strong> ; un tablier est fourni par l'organisatrice si besoin.</li>
                    <li>Merci de <strong>retirer les chaussures</strong> en entrant dans l'atelier.</li>
                    <li>Pensez à apporter <strong>une boîte et un sac</strong> pour que votre enfant puisse ramener sa préparation à la maison.</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Puis-je annuler ma réservation ?</summary>
            <div class="faq-item__answer">
                <p>
                    Oui, vous pouvez annuler votre réservation depuis la rubrique
                    « Mes réservations » de votre espace personnel,
                    <strong>jusqu'à 48 heures avant le début de la séance</strong>.
                    Passé ce délai, l'annulation n'est plus possible en ligne.
                </p>
                <p>
                    En cas d'annulation, vous recevrez une confirmation par e-mail et le
                    remboursement sera effectué sous quelques jours ouvrés.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">La séance peut-elle être annulée par l'organisatrice ?</summary>
            <div class="faq-item__answer">
                <p>
                    Si, <strong>24 heures avant une séance</strong>, le nombre de participants inscrits
                    est inférieur à deux, l'organisatrice se réserve le droit d'annuler la séance.
                </p>
                <p>
                    Dans ce cas, chaque participant est notifié par e-mail et intégralement remboursé
                    dans un délai de quelques jours ouvrés.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">À quelle heure déposer et récupérer mon enfant ?</summary>
            <div class="faq-item__answer">
                <p>
                    Merci d'amener votre enfant <strong>10 minutes avant le début de la séance</strong>
                    afin que l'atelier puisse commencer à l'heure.
                    Le parent (ou son représentant) doit récupérer l'enfant dès <strong>la fin de la séance</strong>.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Comment est structurée une séance ?</summary>
            <div class="faq-item__answer">
                <p>Chaque atelier se déroule en deux temps :</p>
                <ul>
                    <li>
                        <strong>Un temps théorique</strong> : découverte d'un aliment, de son origine,
                        ses propriétés nutritionnelles ou d'un geste technique (pétrir, émulsionner…).
                    </li>
                    <li>
                        <strong>Une application pratique</strong> : réalisation de la recette du jour
                        en toute autonomie guidée. Votre enfant repart avec sa création !
                    </li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Les allergies alimentaires sont-elles prises en compte ?</summary>
            <div class="faq-item__answer">
                <p>
                    Oui. Lors de la réservation, vous pouvez indiquer les allergies de votre enfant.
                    Les fiches de séances précisent également les allergènes présents et les
                    substitutions possibles.
                </p>
            </div>
        </details>

        <details class="faq-item">
            <summary class="faq-item__question">Comment accéder au contenu détaillé d'une séance ?</summary>
            <div class="faq-item__answer">
                <p>
                    Après la séance, l'administrateur confirme la présence de votre enfant.
                    Vous avez alors accès depuis « Mes réservations » au contenu détaillé :
                    recette complète, photos (si vous avez consenti à la prise de photos), etc.
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
        <h2>Prêt à embarquer ?</h2>
        <p>Consultez les prochaines séances disponibles et réservez dès maintenant !</p>
        <div class="about-cta__actions">
            <a href="<?= APP_BASE_URL ?>/ateliers/" class="btn btn--primary btn--lg">🗓️ Voir les séances</a>
            <?php if (!Auth::isLoggedIn()): ?>
                <a href="<?= APP_BASE_URL ?>/register.php" class="btn btn--secondary btn--lg">✏️ Créer un compte</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include ROOT_DIR . '/templates/footer.php'; ?>
