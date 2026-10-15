<?php
/* ============================================================
   Gabarit du site public : en-tête (navigation) + pied de page.
   Partagé par index.php, products.php et contact.php.
   ============================================================ */

const SITE_LINKS = [
    'index.php' => 'Accueil',
    'products.php' => 'Nos Produits',
    'faq.php' => 'FAQ',
    'contact.php' => 'Contact',
];

/** Dossier du site dans l'URL (ex. /infoleader/) : liens valables même depuis la page 404 */
function site_base(): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
}

function site_header(string $title, string $description = '', bool $noindex = false): void
{
    $current = basename($_SERVER['SCRIPT_NAME']);
    $base = site_base();
    // Adresse absolue de la page (Open Graph exige des URL complètes)
    $origin = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
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
    <?php if ($noindex): ?>
        <meta name="robots" content="noindex">
    <?php endif; ?>
    <!-- Open Graph : aperçu lors d'un partage sur les réseaux sociaux -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="InfoLeader">
    <meta property="og:title" content="InfoLeader - <?= e($title) ?>">
    <?php if ($description !== ''): ?>
        <meta property="og:description" content="<?= e($description) ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?= e($origin . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:image" content="<?= e($origin . $base) ?>assets/banniere.webp">
    <link rel="stylesheet" href="<?= e($base) ?>style.css?v=<?= filemtime(ROOT . '/style.css') ?>">
</head>
<body>
    <!-- Lien d'évitement : visible au clavier, saute le menu -->
    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <!-- ====== Barre de navigation ====== -->
    <nav class="navbar">
        <a href="<?= e($base) ?>index.php" class="navbar__brand">Info<span>Leader</span></a>
        <!-- Bouton hamburger (visible sur mobile) -->
        <button id="menu-toggle" class="menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <ul class="navbar__links">
            <?php foreach (SITE_LINKS as $href => $label): ?>
                <li><a href="<?= e($base . $href) ?>"<?= $href === $current ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php
}

function site_footer(): void
{
    $base = site_base();
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
                    <li><a href="<?= e($base . $href) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= e($base) ?>mentions-legales.php">Mentions légales</a></li>
                <li><a href="<?= e($base) ?>admin/">Administration</a></li>
            </ul>
        </div>
        <p class="footer__legal">&copy; <?= date('Y') ?> InfoLeader — Tous droits réservés.</p>
    </footer>

    <script src="<?= e($base) ?>script.js?v=<?= filemtime(ROOT . '/script.js') ?>"></script>
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
    // Lien vers le formulaire de contact, message pré-rempli avec le nom de l'article
    $quote = e(site_base() . 'contact.php?produit=' . rawurlencode($product['name']) . '#contenu');
    // Sans image : grande initiale sur fond teinté
    $image = $product['image']
        ? '<img src="' . e($product['image']) . '" alt="' . $name . '" loading="lazy" decoding="async">'
        : '<span class="product-card__placeholder" aria-hidden="true">' . e(mb_substr($product['name'], 0, 1)) . '</span>';
    return <<<HTML
<article class="product-card" data-name="$name" data-description="$description">
    <div class="product-card__image">$image</div>
    <div class="product-card__body">
        <$headingTag class="product-card__name">$name</$headingTag>
        <p class="product-card__description">$description</p>
        <div class="product-card__footer">
            <p class="product-card__price">$price</p>
            <a class="product-card__quote" href="$quote">Demander un devis</a>
        </div>
    </div>
</article>
HTML;
}
