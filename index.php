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
            <h1 class="hero__title">Bienvenue chez InfoLeader</h1>
            <!-- Slogan engageant -->
            <p class="hero__slogan">La technologie au service de votre réussite.</p>
            <!-- Appel à l'action vers la page "Nos Produits" -->
            <p class="hero__actions">
                <a href="products.php" class="cta">Découvrir nos produits</a>
                <a href="contact.php" class="hero__link">Nous contacter</a>
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
