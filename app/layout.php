<?php
/* ============================================================
   Gabarit des pages d'administration (en-tête + pied de page).
   Reprend la barre de navigation et le style du site (TP1).
   ============================================================ */

/**
 * @param string     $title  Titre de la page
 * @param array|null $admin  Compte connecté (null = pages publiques : connexion, oubli)
 */
function admin_header(string $title, ?array $admin = null, string $subtitle = ''): void
{
    // La politique CSP est envoyée pour toutes les pages par app/bootstrap.php
    $current = basename($_SERVER['SCRIPT_NAME']);
    $links = $admin
        ? ['index.php' => 'Tableau de bord', 'products.php' => 'Articles', 'email.php' => 'E-mails', 'password.php' => 'Mot de passe', 'user.php' => 'Utilisateur', 'maintenance.php' => 'Maintenance']
        : ['../index.php' => 'Retour au site', 'login.php' => 'Connexion'];
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>InfoLeader - <?= e($title) ?></title>
    <link rel="stylesheet" href="../style.css?v=<?= filemtime(ROOT . '/style.css') ?>">
</head>
<body class="admin">

    <!-- ====== Barre de navigation de l'administration ====== -->
    <nav class="navbar">
        <div class="navbar__brand">Info<span>Leader</span> <small class="navbar__tag">admin</small></div>
        <button id="menu-toggle" class="menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <ul class="navbar__links">
            <?php foreach ($links as $href => $label): ?>
                <li><a href="<?= e($href) ?>"<?= $href === $current ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
            <?php endforeach; ?>
            <?php if ($admin): ?>
                <li>
                    <form action="logout.php" method="post" class="navbar__logout">
                        <?= csrf_field() ?>
                        <button type="submit">Déconnexion</button>
                    </form>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <div class="page-header__inner">
            <h1><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?>
                <p class="page-header__sub"><?= e($subtitle) ?></p>
            <?php endif; ?>
        </div>
    </header>

    <main class="admin-wrap">
        <?= render_flashes() ?>
<?php
}

function admin_footer(): void
{
    ?>
    </main>

    <footer class="footer">
        &copy; 2026 InfoLeader — Administration
    </footer>

    <script src="../script.js?v=<?= filemtime(ROOT . '/script.js') ?>"></script>
</body>
</html>
<?php
}

/** Liste des règles de mot de passe affichée sous les formulaires */
function password_rules_html(): string
{
    $html = '<ul class="rules">';
    foreach (PASSWORD_RULES as $rule) {
        $html .= '<li>' . e($rule) . '</li>';
    }
    return $html . '</ul>';
}
