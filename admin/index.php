<?php
/* ============================================================
   Tableau de bord : compte connecté + accès aux modules
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';
require ROOT . '/app/mailer.php';

$admin = require_login();

// Durée de conservation (RGPD) : à chaque ouverture du tableau de bord,
// les journaux de plus de 12 mois et les compteurs anti force brute périmés sont supprimés
$purged = db()->exec('DELETE FROM logs WHERE created_at < NOW() - INTERVAL 12 MONTH');
if ($purged > 0) {
    log_event('event', "Conservation : $purged entrée(s) de journal de plus de 12 mois supprimée(s)");
}
db()->prepare('DELETE FROM throttle WHERE blocked_until < ? AND updated_at < ?')
    ->execute([time(), time() - THROTTLE_FORGET_AFTER]);

// Dernière connexion réussie précédente (hors session en cours)
$stmt = db()->prepare("SELECT created_at, ip FROM logs WHERE type = 'login_ok' AND message = ? ORDER BY id DESC LIMIT 1 OFFSET 1");
$stmt->execute(['Connexion réussie : ' . $admin['email']]);
$previous = $stmt->fetch();

$modules = [
    ['email.php', 'Configuration des e-mails', mail_ready() ? 'Envoi configuré (' . (mail_config()['transport'] === 'smtp' ? 'Mailjet SMTP' : 'PHP mail') . ').' : 'À configurer : l\'envoi des codes en dépend.'],
    ['products.php', 'Articles', 'Ajouter, modifier ou retirer les articles du catalogue.'],
    ['password.php', 'Mot de passe', 'Changer le mot de passe administrateur.'],
    ['user.php', 'Utilisateur', 'Modifier l\'adresse e-mail du compte.'],
    ['maintenance.php', 'Maintenance', 'Système, journaux, sauvegarde, fichiers temporaires.'],
];

admin_header('Tableau de bord', $admin);
?>
<section class="panel">
    <h2>Compte connecté</h2>
    <p><strong><?= e($admin['email']) ?></strong></p>
    <p class="muted">
        Session ouverte à <?= date('H:i', $_SESSION['login_time']) ?> depuis <?= e(client_ip()) ?>.
        <?php if ($previous): ?>
            Connexion précédente : <?= e($previous['created_at']) ?> (<?= e($previous['ip']) ?>).
        <?php endif; ?>
    </p>
</section>

<div class="module-grid">
    <?php foreach ($modules as [$href, $title, $text]): ?>
        <a class="module-card" href="<?= e($href) ?>">
            <h3><?= e($title) ?></h3>
            <p><?= e($text) ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php admin_footer();
