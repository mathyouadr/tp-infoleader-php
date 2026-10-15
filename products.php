<?php
/* ============================================
   Page "Nos Produits"
   Articles lus en base (gérés dans Administration > Articles),
   filtrés en temps réel par script.js.
   ============================================ */

require __DIR__ . '/app/bootstrap.php';
require ROOT . '/app/site.php';
require ROOT . '/app/catalog.php';

$products = products();
$count = count($products);

site_header('Nos Produits', 'Claviers, écrans, souris, casques : le catalogue InfoLeader.');
?>

    <!-- ====== En-tête de page ====== -->
    <header class="page-header">
        <div class="page-header__inner">
            <h1>Nos Produits</h1>
            <p class="page-header__sub">
                Du matériel informatique sélectionné pour sa fiabilité et sa performance.
            </p>
        </div>
    </header>

    <main class="products-wrap" id="contenu">

        <?php if ($count > 0): ?>

            <!-- ====== Filtre de recherche (temps réel) ====== -->
            <div class="toolbar">
                <div class="search-bar">
                    <label for="product-search" class="search-bar__label">Rechercher un produit</label>
                    <input type="search" id="product-search" class="search-bar__input" placeholder="Ex. : clavier, souris, 4K...">
                </div>
                <p id="products-count" class="toolbar__count" aria-live="polite"><?= $count ?> article<?= $count > 1 ? 's' : '' ?></p>
            </div>

            <!-- ====== Grille des produits ====== -->
            <div id="products-grid" class="products-grid">
                <?php foreach ($products as $product): ?>
                    <?= product_card($product) ?>
                <?php endforeach; ?>
            </div>
            <p id="products-no-match" class="products-empty" hidden>
                Aucun article ne correspond à votre recherche. Essayez un autre mot, par exemple « clavier ».
            </p>

        <?php else: ?>

            <!-- Message si aucun produit n'est disponible -->
            <p class="products-empty">Aucun produit disponible pour le moment.</p>

        <?php endif; ?>

    </main>

<?php site_footer();
