<?php
/* ============================================
   Page d'accueil : bannière, nouveautés, présentation
   Les articles viennent de la base (Administration > Articles).
   ============================================ */

require __DIR__ . '/app/bootstrap.php';
require ROOT . '/app/site.php';
require ROOT . '/app/catalog.php';

$latest = products(3);
$total = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();

site_header('Accueil', 'InfoLeader : matériel informatique sélectionné pour sa fiabilité et sa performance.');
?>

    <!-- ====== Bannière visuelle ====== -->
    <header class="hero" id="contenu">
        <!-- Image de la bannière placée dans un <div> -->
        <div class="hero__visual">
            <img src="assets/banniere.webp" alt="" width="1584" height="396" fetchpriority="high">
        </div>
        <div class="hero__inner">
            <h1 class="hero__title">Du matériel informatique choisi pour durer</h1>
            <!-- Slogan engageant -->
            <p class="hero__slogan">Claviers, écrans, casques et accessoires sélectionnés pour leur fiabilité. Un devis personnalisé pour les entreprises comme pour les particuliers.</p>
            <!-- Appel à l'action vers la page "Nos Produits" -->
            <p class="hero__actions">
                <a href="products.php" class="cta">Voir le catalogue</a>
                <a href="contact.php" class="hero__link">Demander un devis</a>
            </p>
        </div>
    </header>

    <!-- ====== Nouveautés (3 derniers articles) ====== -->
    <?php if ($latest): ?>
        <section class="shelf" aria-labelledby="shelf-title">
            <div class="shelf__head">
                <h2 id="shelf-title">Nouveautés</h2>
                <a href="products.php">Voir les <?= $total ?> articles</a>
            </div>
            <div class="products-grid">
                <?php foreach ($latest as $product): ?>
                    <?= product_card($product, 'h3') ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ====== Parcours d'achat : une vraie séquence, d'où la numérotation ====== -->
    <section class="steps" aria-labelledby="steps-title">
        <h2 id="steps-title" class="steps__title">Obtenir votre matériel en trois étapes</h2>
        <ol class="steps__list">
            <li>
                <h3>Choisissez</h3>
                <p>Parcourez le <a href="products.php">catalogue</a> et repérez les articles qui vous intéressent.</p>
            </li>
            <li>
                <h3>Demandez un devis</h3>
                <p>Cliquez sur « Demander un devis » sous l'article : le formulaire est déjà pré-rempli.</p>
            </li>
            <li>
                <h3>Recevez notre réponse</h3>
                <p>Nous vous répondons par e-mail avec le prix exact et nos conseils.</p>
            </li>
        </ol>
    </section>

    <!-- ====== Paragraphe d'introduction ====== -->
    <section class="intro">
        <h2 class="intro__heading">Qui sommes-nous&nbsp;?</h2>
        <p class="intro__text">
            Fondée en 2020, InfoLeader conçoit des solutions numériques fiables
            pour les entreprises et les particuliers. Notre équipe passionnée
            met son expertise en développement, infrastructure et conseil
            au service de votre réussite. Nous croyons qu'une bonne technologie
            doit être simple, performante et accessible à tous.
        </p>
    </section>

<?php site_footer();
