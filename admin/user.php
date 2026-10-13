<?php
/* ============================================================
   Module « Utilisateur » : changement de l'adresse e-mail
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';

$admin = require_login();
$wait = 0;
$newEmail = '';

if (is_post()) {
    $current = (string) ($_POST['current'] ?? '');
    $newEmail = trim((string) ($_POST['email'] ?? ''));
    $confirm = trim((string) ($_POST['email_confirm'] ?? ''));

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);

    $refusal = throttle_check('password');
    if ($refusal !== null) {
        flash('error', $refusal);
    } elseif (!password_verify($current, (string) $stmt->fetchColumn())) {
        $wait = throttle_fail('password');
        log_event('login_fail', 'Mot de passe actuel incorrect (module Utilisateur) : ' . $admin['email']);
        flash('error', 'Le mot de passe actuel est incorrect. ' . wait_message($wait));
    } else {
        throttle_reset('password');
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 254) {
            flash('error', 'La nouvelle adresse e-mail n\'est pas valide.');
        } elseif (strcasecmp($newEmail, $admin['email']) === 0) {
            flash('error', 'La nouvelle adresse est identique à l\'adresse actuelle.');
        } elseif ($newEmail !== $confirm) {
            flash('error', 'Les deux adresses ne correspondent pas.');
        } else {
            db()->prepare('UPDATE admins SET email = ? WHERE id = ?')->execute([$newEmail, $admin['id']]);
            log_event('event', 'Adresse e-mail modifiée : ' . $admin['email'] . ' → ' . $newEmail);
            flash('success', 'Adresse e-mail mise à jour : ' . $newEmail);
            redirect('user.php');
        }
    }
}

admin_header('Utilisateur', $admin, 'Modifier l\'adresse e-mail du compte administrateur.');
?>
<section class="panel admin-form">
    <h2>Adresse actuelle</h2>
    <p><strong><?= e($admin['email']) ?></strong></p>
    <p class="muted">Elle sert d'identifiant de connexion et reçoit les codes de réinitialisation.</p>
</section>

<form action="user.php" method="post" class="contact-form admin-form" <?= $wait > 0 && $wait < THROTTLE_BLOCK_TIME ? 'data-wait="' . $wait . '"' : '' ?>>
    <?= csrf_field() ?>
    <div class="form-control">
        <label for="email">Nouvelle adresse e-mail</label>
        <input type="email" id="email" name="email" value="<?= e($newEmail) ?>" required maxlength="254">
    </div>
    <div class="form-control">
        <label for="email_confirm">Confirmation de la nouvelle adresse</label>
        <input type="email" id="email_confirm" name="email_confirm" required maxlength="254">
    </div>
    <div class="form-control">
        <label for="current">Mot de passe actuel</label>
        <input type="password" id="current" name="current" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn-submit">Mettre à jour l'adresse</button>
</form>
<?php admin_footer();
