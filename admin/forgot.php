<?php
/* ============================================================
   Mot de passe oublié : envoi d'un code à 8 chiffres (5 min)
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';
require ROOT . '/app/mailer.php';

const RESET_CODE_LIFETIME = 300; // 5 minutes

$email = '';

if (is_post()) {
    $email = trim((string) ($_POST['email'] ?? ''));

    $refusal = throttle_check('forgot');
    if ($refusal !== null) {
        flash('error', $refusal);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Adresse e-mail invalide.');
    } else {
        // Chaque demande compte : limite le spam de codes (3 s, 6 s, 12 s... puis blocage)
        throttle_fail('forgot');

        $stmt = db()->prepare('SELECT id, email FROM admins WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            // Un seul code valide à la fois
            db()->prepare('UPDATE password_resets SET used = 1 WHERE admin_id = ?')->execute([$admin['id']]);

            $code = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            db()->prepare('INSERT INTO password_resets (admin_id, code_hash, expires_at) VALUES (?, ?, ?)')
                ->execute([$admin['id'], password_hash($code, PASSWORD_DEFAULT), time() + RESET_CODE_LIFETIME]);

            $error = send_mail(
                $admin['email'],
                'InfoLeader - Code de réinitialisation',
                "Bonjour,\n\nVotre code de réinitialisation est : $code\n\n"
                . "Il est valable 5 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n\n"
                . "InfoLeader"
            );
            log_event('event', $error === null
                ? 'Code de réinitialisation envoyé à ' . $admin['email']
                : 'Code de réinitialisation NON envoyé (erreur e-mail)');
        } else {
            log_event('event', 'Demande de réinitialisation pour une adresse inconnue : ' . $email);
        }

        // Réponse identique dans tous les cas : ne révèle pas si le compte existe
        $_SESSION['reset_email'] = $email;
        flash('success', 'Si cette adresse correspond au compte administrateur, un code à 8 chiffres vient d\'être envoyé. Il est valable 5 minutes.');
        redirect('reset.php');
    }
}

admin_header('Mot de passe oublié', null, 'Recevez un code de réinitialisation par e-mail.');
?>
<form action="forgot.php" method="post" class="contact-form admin-form">
    <?= csrf_field() ?>
    <div class="form-control">
        <label for="email">Adresse e-mail du compte</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
    </div>
    <button type="submit" class="btn-submit">Recevoir un code</button>
    <p class="form-link"><a href="reset.php">J'ai déjà un code</a> · <a href="login.php">Retour à la connexion</a></p>
</form>
<?php admin_footer();
