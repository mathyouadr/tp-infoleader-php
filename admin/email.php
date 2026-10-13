<?php
/* ============================================================
   Module « Configuration des e-mails » : PHP mail ou Mailjet SMTP
   Le mot de passe SMTP est chiffré en base et jamais réaffiché.
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';
require ROOT . '/app/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;

$admin = require_login();
$config = mail_config();

if (is_post()) {
    $one = fn (string $key) => trim(str_replace(["\r", "\n"], '', (string) ($_POST[$key] ?? '')));
    $new = [
        'transport' => $one('transport') === 'phpmail' ? 'phpmail' : 'smtp',
        'from_name' => mb_substr($one('from_name'), 0, 100),
        'from_email' => $one('from_email'),
        'to_email' => $one('to_email'),
        'smtp_host' => $one('smtp_host'),
        'smtp_port' => (int) $one('smtp_port'),
        'smtp_secure' => in_array($one('smtp_secure'), ['tls', 'ssl', 'none'], true) ? $one('smtp_secure') : 'tls',
        'smtp_user' => $one('smtp_user'),
        'smtp_pass' => $config['smtp_pass'], // conservé si le champ reste vide
        'debug' => isset($_POST['debug']),
    ];
    $password = (string) ($_POST['smtp_pass'] ?? '');
    if ($password !== '') {
        $new['smtp_pass'] = encrypt_secret($password);
    }

    // Validation côté serveur
    $errors = [];
    if (!filter_var($new['from_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse de l\'expéditeur invalide.';
    }
    if (!filter_var($new['to_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse du destinataire invalide.';
    }
    if ($new['transport'] === 'smtp') {
        if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $new['smtp_host'])) {
            $errors[] = 'Serveur SMTP invalide.';
        }
        if ($new['smtp_port'] < 1 || $new['smtp_port'] > 65535) {
            $errors[] = 'Port SMTP invalide (1 à 65535).';
        }
        if ($new['smtp_user'] === '') {
            $errors[] = 'L\'identifiant SMTP est requis.';
        }
        if ($new['smtp_pass'] === '') {
            $errors[] = 'Le mot de passe (clé SMTP) est requis.';
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        $config = $new; // réaffiche la saisie (hors mot de passe)
    } else {
        set_setting('mail', $new);
        log_event('event', 'Configuration e-mail enregistrée (' . $new['transport'] . ') par ' . $admin['email']);
        flash('success', 'Configuration enregistrée.');

        if (($_POST['action'] ?? '') === 'test') {
            $error = send_mail(
                $new['to_email'],
                'InfoLeader - E-mail de test',
                "Ceci est un e-mail de test envoyé depuis l'administration InfoLeader le " . date('d/m/Y à H:i:s') . ".\n\nLa configuration fonctionne."
            );
            if ($error === null) {
                log_event('event', 'E-mail de test envoyé à ' . $new['to_email']);
                flash('success', 'E-mail de test accepté par le serveur pour ' . $new['to_email'] . '. Vérifiez la réception (et les spams).');
            } else {
                flash('error', 'Échec de l\'envoi : ' . $error);
            }
        }
        redirect('email.php');
    }
}

$smtp = $config['transport'] === 'smtp';
admin_header('Configuration des e-mails', $admin, 'PHP mail ou Mailjet SMTP (recommandé sous WAMP).');
?>
<section class="panel">
    <h2>Prérequis</h2>
    <p>
        OpenSSL : <?= extension_loaded('openssl') ? '<span class="badge badge--ok">activé</span>' : '<span class="badge badge--ko">absent</span>' ?>
        · PHPMailer : <?= class_exists(PHPMailer::class) ? '<span class="badge badge--ok">' . e(PHPMailer::VERSION) . '</span>' : '<span class="badge badge--ko">absent (composer install)</span>' ?>
        · Envoi : <?= mail_ready($config) ? '<span class="badge badge--ok">configuré</span>' : '<span class="badge badge--warn">incomplet</span>' ?>
    </p>
    <p class="muted">Sous WAMP, PHP mail() n'a pas de serveur d'envoi par défaut : utilisez Mailjet SMTP.</p>
</section>

<form action="email.php" method="post" class="contact-form admin-form" autocomplete="off">
    <?= csrf_field() ?>

    <div class="form-control">
        <label for="transport">Méthode d'envoi</label>
        <select id="transport" name="transport">
            <option value="smtp"<?= $smtp ? ' selected' : '' ?>>Mailjet SMTP (PHPMailer)</option>
            <option value="phpmail"<?= $smtp ? '' : ' selected' ?>>PHP mail()</option>
        </select>
    </div>
    <div class="form-control">
        <label for="from_name">Nom de l'expéditeur</label>
        <input type="text" id="from_name" name="from_name" value="<?= e($config['from_name']) ?>" maxlength="100">
    </div>
    <div class="form-control">
        <label for="from_email">Adresse de l'expéditeur</label>
        <input type="email" id="from_email" name="from_email" value="<?= e($config['from_email']) ?>" required>
        <small class="form-hint">Doit être une adresse expéditrice validée dans Mailjet (Sender addresses &amp; domains).</small>
    </div>
    <div class="form-control">
        <label for="to_email">Adresse du destinataire (tests et messages de contact)</label>
        <input type="email" id="to_email" name="to_email" value="<?= e($config['to_email']) ?>" required>
    </div>

    <h3>Paramètres SMTP</h3>
    <div class="form-control">
        <label for="smtp_host">Serveur SMTP</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?= e($config['smtp_host']) ?>">
    </div>
    <div class="form-control">
        <label for="smtp_port">Port</label>
        <input type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?= (int) $config['smtp_port'] ?>">
    </div>
    <div class="form-control">
        <label for="smtp_secure">Sécurité</label>
        <select id="smtp_secure" name="smtp_secure">
            <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'Aucune (déconseillé)'] as $value => $label): ?>
                <option value="<?= $value ?>"<?= $config['smtp_secure'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-control">
        <label for="smtp_user">Identifiant SMTP (API Key Mailjet)</label>
        <input type="text" id="smtp_user" name="smtp_user" value="<?= e($config['smtp_user']) ?>" autocomplete="off">
        <small class="form-hint">Mailjet : Account settings → API Key Management → « API Key ».</small>
    </div>
    <div class="form-control">
        <label for="smtp_pass">Mot de passe SMTP (Secret Key Mailjet)</label>
        <input type="password" id="smtp_pass" name="smtp_pass" value="" autocomplete="new-password"
               placeholder="<?= $config['smtp_pass'] !== '' ? '•••••••• enregistré — laisser vide pour conserver' : 'Secret Key' ?>">
        <small class="form-hint">Stocké chiffré (AES-256-GCM), jamais réaffiché ni journalisé.</small>
    </div>
    <div class="form-check">
        <label>
            <input type="checkbox" name="debug" value="1"<?= $config['debug'] ? ' checked' : '' ?>>
            Activer les logs de débogage SMTP (storage/logs/smtp-debug.log, identifiants masqués)
        </label>
    </div>

    <p>
        <button type="submit" name="action" value="save" class="btn">Enregistrer</button>
        <button type="submit" name="action" value="test" class="btn btn--light">Enregistrer et envoyer un test</button>
    </p>
</form>
<?php admin_footer();
