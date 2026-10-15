<?php
/* ============================================
   Page 404 : adresse introuvable
   Appelée par Apache (ErrorDocument dans .htaccess).
   ============================================ */

require __DIR__ . '/app/bootstrap.php';
require ROOT . '/app/site.php';

http_response_code(404);
site_header('Page introuvable');
?>

    <header class="page-header">
        <div class="page-header__inner">
            <h1>Page introuvable</h1>
            <p class="page-header__sub">L'adresse demandée n'existe pas ou a été déplacée.</p>
        </div>
    </header>

    <main class="legal-wrap" id="contenu">
        <p><a href="<?= e(site_base()) ?>index.php" class="cta">Retour à l'accueil</a></p>
    </main>

<?php site_footer();
