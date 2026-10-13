<?php
/* ============================================================
   Connexion à l'administration (anti force brute par IP)
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';

if (current_admin()) {
    redirect('index.php');
}

$email = '';
$wait = 0; // secondes pendant lesquelles le bouton est désactivé (confort, la vraie limite est serveur)

if (is_post()) {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $refusal = throttle_check('login');
    if ($refusal !== null) {
        // Tentative trop rapide ou IP bloquée : le mot de passe n'est même pas vérifié
        flash('error', $refusal);
    } else {
        $stmt = db()->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            throttle_reset('login');
            login($admin);
            log_event('login_ok', 'Connexion réussie : ' . $admin['email']);
            flash('success', 'Connexion réussie. Bienvenue !');
            redirect('index.php');
        }

        $wait = throttle_fail('login');
        log_event('login_fail', 'Échec de connexion pour « ' . mb_substr($email, 0, 254) . ' »');
        flash('error', 'Identifiants incorrects. ' . wait_message($wait));
    }
}

admin_header('Connexion', null, 'Espace réservé à l\'administrateur du site.');
?>
<form action="login.php" method="post" class="contact-form admin-form" <?= $wait > 0 && $wait < THROTTLE_BLOCK_TIME ? 'data-wait="' . $wait . '"' : '' ?>>
    <?= csrf_field() ?>
    <div class="form-control">
        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
    </div>
    <div class="form-control">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn-submit">Se connecter</button>
    <p class="form-link"><a href="forgot.php">Mot de passe oublié ?</a></p>
</form>
<?php admin_footer();
