<?php
/* ============================================================
   Vérification du code + choix du nouveau mot de passe
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';

const RESET_MAX_ATTEMPTS = 5; // au-delà, le code est invalidé

$email = (string) ($_SESSION['reset_email'] ?? '');
$wait = 0;

if (is_post()) {
    $email = trim((string) ($_POST['email'] ?? ''));
    $code = trim((string) ($_POST['code'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    $refusal = throttle_check('reset');
    $errors = password_errors($password);
    if ($password !== $confirm) {
        $errors[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
    }

    if ($refusal !== null) {
        flash('error', $refusal);
    } elseif (!preg_match('/^\d{8}$/', $code)) {
        flash('error', 'Le code doit comporter exactement 8 chiffres.');
    } elseif ($errors) {
        // Règles vérifiées avant le code : une faute de saisie ne consomme pas d'essai
        foreach ($errors as $error) {
            flash('error', $error);
        }
    } else {
        // Dernier code non utilisé et non expiré du compte
        $stmt = db()->prepare(
            'SELECT r.id, r.code_hash, r.attempts, a.id AS admin_id, a.email
             FROM password_resets r JOIN admins a ON a.id = r.admin_id
             WHERE a.email = ? AND r.used = 0 AND r.expires_at >= ?
             ORDER BY r.id DESC LIMIT 1'
        );
        $stmt->execute([$email, time()]);
        $reset = $stmt->fetch();

        if ($reset && password_verify($code, $reset['code_hash'])) {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['admin_id']]);
            db()->prepare('UPDATE password_resets SET used = 1 WHERE admin_id = ?')->execute([$reset['admin_id']]);
            throttle_reset('reset');
            throttle_reset('login');
            unset($_SESSION['reset_email']);
            log_event('event', 'Mot de passe réinitialisé par code : ' . $reset['email']);
            flash('success', 'Mot de passe modifié. Vous pouvez vous connecter.');
            redirect('login.php');
        }

        // Code faux, expiré ou compte inconnu : même message
        if ($reset) {
            // MySQL évalue les affectations de gauche à droite : « attempts » vaut déjà la nouvelle valeur
            db()->prepare('UPDATE password_resets SET attempts = attempts + 1, used = (attempts >= ?) WHERE id = ?')
                ->execute([RESET_MAX_ATTEMPTS, $reset['id']]);
        }
        $wait = throttle_fail('reset');
        log_event('login_fail', 'Code de réinitialisation invalide pour « ' . mb_substr($email, 0, 254) . ' »');
        flash('error', 'Code invalide ou expiré. ' . wait_message($wait));
    }
}

admin_header('Nouveau mot de passe', null, 'Saisissez le code reçu par e-mail (valable 5 minutes).');
?>
<form action="reset.php" method="post" class="contact-form admin-form" <?= $wait > 0 && $wait < THROTTLE_BLOCK_TIME ? 'data-wait="' . $wait . '"' : '' ?>>
    <?= csrf_field() ?>
    <div class="form-control">
        <label for="email">Adresse e-mail du compte</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username">
    </div>
    <div class="form-control">
        <label for="code">Code à 8 chiffres</label>
        <input type="text" id="code" name="code" inputmode="numeric" pattern="\d{8}" maxlength="8" required autocomplete="one-time-code">
    </div>
    <div class="form-control">
        <label for="password">Nouveau mot de passe</label>
        <input type="password" id="password" name="password" minlength="20" required autocomplete="new-password">
        <?= password_rules_html() ?>
    </div>
    <div class="form-control">
        <label for="password_confirm">Confirmation</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="20" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-submit">Changer le mot de passe</button>
    <p class="form-link"><a href="forgot.php">Renvoyer un code</a> · <a href="login.php">Retour à la connexion</a></p>
</form>
<?php admin_footer();
