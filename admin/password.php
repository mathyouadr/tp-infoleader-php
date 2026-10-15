<?php
/* ============================================================
   Module « Mot de passe » : changement du mot de passe admin
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';

$admin = require_login();
$wait = 0;

if (is_post()) {
    $current = (string) ($_POST['current'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $hash = (string) $stmt->fetchColumn();

    $refusal = throttle_check('password');
    if ($refusal !== null) {
        flash('error', $refusal);
    } elseif (!password_verify($current, $hash)) {
        $wait = throttle_fail('password');
        log_event('login_fail', 'Mot de passe actuel incorrect (module Mot de passe) : ' . $admin['email']);
        flash('error', 'Le mot de passe actuel est incorrect. ' . wait_message($wait));
    } else {
        throttle_reset('password');
        $errors = password_errors($password);
        if ($password !== $confirm) {
            $errors[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
        }
        if (!$errors && password_verify($password, $hash)) {
            $errors[] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('error', $error);
            }
        } else {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$newHash, $admin['id']]);
            session_regenerate_id(true);
            $_SESSION['password_fp'] = password_fingerprint($newHash); // seule cette session reste ouverte
            log_event('event', 'Mot de passe modifié : ' . $admin['email']);
            flash('success', 'Mot de passe modifié avec succès.');
            redirect('password.php');
        }
    }
}

admin_header('Mot de passe', $admin, 'Changer le mot de passe du compte administrateur.');
?>
<form action="password.php" method="post" class="contact-form admin-form" <?= $wait > 0 && $wait < THROTTLE_BLOCK_TIME ? 'data-wait="' . $wait . '"' : '' ?>>
    <?= csrf_field() ?>
    <div class="form-control">
        <label for="current">Mot de passe actuel</label>
        <input type="password" id="current" name="current" required autocomplete="current-password">
    </div>
    <div class="form-control">
        <label for="password">Nouveau mot de passe</label>
        <input type="password" id="password" name="password" minlength="20" required autocomplete="new-password">
        <?= password_rules_html() ?>
    </div>
    <div class="form-control">
        <label for="password_confirm">Confirmation du nouveau mot de passe</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="20" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-submit">Changer le mot de passe</button>
</form>
<?php admin_footer();
