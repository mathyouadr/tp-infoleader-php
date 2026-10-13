<?php
/* ============================================================
   Gabarit du site public : en-tête (navigation) + pied de page.
   Partagé par index.php, products.php et contact.php.
   ============================================================ */

const SITE_LINKS = [
    'index.php' => 'Accueil',
    'products.php' => 'Nos Produits',
    'contact.php' => 'Contact',
];

function site_header(string $title, string $description = ''): void
{
    $current = basename($_SERVER['SCRIPT_NAME']);
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InfoLeader - <?= e($title) ?></title>
    <?php if ($description !== ''): ?>
        <meta name="description" content="<?= e($description) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(ROOT . '/style.css') ?>">
</head>
<body>

    <!-- ====== Barre de navigation ====== -->
    <nav class="navbar">
        <a href="index.php" class="navbar__brand">Info<span>Leader</span></a>
        <!-- Bouton hamburger (visible sur mobile) -->
        <button id="menu-toggle" class="menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <ul class="navbar__links">
            <?php foreach (SITE_LINKS as $href => $label): ?>
                <li><a href="<?= $href ?>"<?= $href === $current ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php
}

function site_footer(): void
{
    ?>
    <!-- ====== Pied de page ====== -->
    <footer class="footer">
        <div class="footer__inner">
            <div>
                <p class="footer__brand">Info<span>Leader</span></p>
                <p>Matériel informatique et conseil, depuis 2020.</p>
            </div>
            <ul class="footer__links">
                <?php foreach (SITE_LINKS as $href => $label): ?>
                    <li><a href="<?= $href ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
                <li><a href="admin/">Administration</a></li>
            </ul>
        </div>
        <p class="footer__legal">&copy; <?= date('Y') ?> InfoLeader — Tous droits réservés.</p>
    </footer>

    <script src="script.js?v=<?= filemtime(ROOT . '/script.js') ?>"></script>
</body>
</html>
<?php
}

/** Carte produit (page Produits et accueil) */
function product_card(array $product, string $headingTag = 'h2'): string
{
    $name = e($product['name']);
    $description = e($product['description']);
    $price = e(format_price($product['price']));
    // Sans image : grande initiale sur fond teinté
    $image = $product['image']
        ? '<img src="' . e($product['image']) . '" alt="" loading="lazy">'
        : '<span class="product-card__placeholder" aria-hidden="true">' . e(mb_substr($product['name'], 0, 1)) . '</span>';
    return <<<HTML
<article class="product-card" data-name="$name" data-description="$description">
    <div class="product-card__image">$image</div>
    <div class="product-card__body">
        <$headingTag class="product-card__name">$name</$headingTag>
        <p class="product-card__description">$description</p>
        <p class="product-card__price">$price</p>
    </div>
</article>
HTML;
}
