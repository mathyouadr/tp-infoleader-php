<?php
/* ============================================================
   Tests HTTP de bout en bout (contre Apache/WAMP en local).
   Usage : php tests/http_test.php
   Le compte admin est sauvegardé puis restauré à la fin ;
   la limitation de débit de 127.0.0.1 est remise à zéro.
   ============================================================ */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/mailer.php';
require __DIR__ . '/../app/catalog.php';

const BASE = 'http://127.0.0.1/infoleader-tp2ter/';
const TEST_PASSWORD = 'Test-Password-2026-Abcdef!';
const NEW_PASSWORD = 'Nouveau-Mot2Passe-Tres-Long!';

// WAMP démarré ? Sinon, message clair au lieu de « Erreur interne »
try {
    db()->query('SELECT 1');
    $ch = curl_init(BASE);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_CONNECTTIMEOUT => 3]);
    $up = curl_exec($ch) !== false;
    curl_close($ch);
} catch (PDOException) {
    $up = false;
}
if (!$up) {
    fwrite(STDERR, "Apache ou MySQL ne répond pas : démarrez WAMP (icône verte), puis relancez les tests.\n");
    exit(1);
}

$jar = tempnam(sys_get_temp_dir(), 'il');
$passed = 0;
$failed = 0;
$skipped = 0; // vérifications ignorées faute de réseau (jamais comptées comme réussies)

function check(bool $ok, string $label): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  ÉCHEC ') . $label . PHP_EOL;
}

/** Requête HTTP avec cookies ; renvoie [code, corps, Location] */
function http(string $path, ?array $post = null): array
{
    global $jar;
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
    ]);
    if ($post !== null) {
        // Envoi multipart dès qu'un fichier est joint
        $hasFile = (bool) array_filter($post, fn ($v) => $v instanceof CURLFile);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $post : http_build_query($post));
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSize);
    preg_match('/^Location: (.*)$/mi', $headers, $m);
    $rawBody = substr($raw, $headerSize);
    return [$code, html_entity_decode($rawBody, ENT_QUOTES), trim($m[1] ?? ''), $headers, $rawBody];
}

/** Jeton CSRF de la page (GET) */
function csrf(string $path): string
{
    [, $body] = http($path);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m);
    return $m[1] ?? '';
}

/** Le serveur SMTP de Mailjet répond-il ? (il peut bloquer un moment après des essais répétés) */
function mailjet_up(): bool
{
    $socket = @fsockopen('in-v3.mailjet.com', 587, $errno, $errstr, 5);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 5);
    return str_starts_with((string) fgets($socket), '220');
}

function clear_throttle(): void
{
    db()->exec("DELETE FROM throttle WHERE ip IN ('127.0.0.1', '::1')");
}

function login_as(string $email, string $password): array
{
    return http('admin/login.php', ['csrf' => csrf('admin/login.php'), 'email' => $email, 'password' => $password]);
}

// Sauvegarde du compte réel, remplacé par un mot de passe connu
$admin = db()->query('SELECT * FROM admins ORDER BY id LIMIT 1')->fetch();
$email = $admin['email'];
db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
    ->execute([password_hash(TEST_PASSWORD, PASSWORD_DEFAULT), $admin['id']]);
clear_throttle();
$mailBackup = db()->query("SELECT value FROM settings WHERE name = 'mail'")->fetchColumn();
// Envoi désactivé pendant les tests (expéditeur vide) : aucun vrai e-mail ne part
set_setting('mail', ['from_email' => ''] + mail_config());

try {
    echo "\n== 1. Connexion & anti force brute ==\n";

    [$code, , $loc] = http('admin/index.php');
    check($code === 302 && str_contains($loc, 'login.php'), 'Tableau de bord inaccessible sans connexion');

    [$code] = http('admin/login.php', ['email' => $email, 'password' => TEST_PASSWORD]);
    check($code === 400, 'POST sans jeton CSRF refusé (400)');

    [, $body] = login_as($email, 'mauvais');
    check(str_contains($body, 'Identifiants incorrects') && str_contains($body, '3 s'), '1er échec : attente de 3 s annoncée');
    check(str_contains($body, 'data-wait="3"'), 'Bouton désactivé 3 s côté client');

    [$code, $body] = login_as($email, TEST_PASSWORD);
    check($code === 200 && str_contains($body, 'patienter'), 'Bon mot de passe refusé pendant l\'attente (serveur)');

    sleep(4);
    [, $body] = login_as($email, 'mauvais');
    check(str_contains($body, '6 s'), '2e échec : délai progressif 6 s');

    // Simule 4 échecs passés pour tester le blocage sans attendre 45 s
    db()->exec("UPDATE throttle SET failures = 4, next_allowed = 0 WHERE scope = 'login'");
    [, $body] = login_as($email, 'mauvais');
    check(str_contains($body, 'bloquée'), '5e échec : IP bloquée');
    [, $body] = login_as($email, TEST_PASSWORD);
    check(str_contains($body, 'bloquée jusqu'), 'Blocage maintenu après rechargement, même avec le bon mot de passe');
    $blocked = db()->query("SELECT COUNT(*) FROM logs WHERE type = 'blocked' AND created_at >= NOW() - INTERVAL 1 MINUTE")->fetchColumn();
    check($blocked > 0, 'Blocage journalisé');

    clear_throttle();
    [$code, , $loc] = login_as($email, TEST_PASSWORD);
    check($code === 302 && str_contains($loc, 'index.php'), 'Connexion réussie → tableau de bord');
    [$code, $body] = http('admin/index.php');
    check($code === 200 && str_contains($body, $email), 'Tableau de bord affiche le compte connecté');
    foreach (['email.php', 'password.php', 'user.php', 'maintenance.php'] as $module) {
        check(str_contains($body, 'href="' . $module . '"'), "Lien vers le module $module");
    }

    // Conservation 12 mois (RGPD-3) : purge à l'ouverture du tableau de bord
    $old = db()->prepare("INSERT INTO logs (type, ip, message, created_at) VALUES ('contact', '127.0.0.1', ?, NOW() - INTERVAL ? MONTH)");
    $old->execute(['rgpd3-ancien', 13]);
    $old->execute(['rgpd3-recent', 11]);
    http('admin/index.php');
    $left = db()->query("SELECT message FROM logs WHERE message LIKE 'rgpd3-%'")->fetchAll(PDO::FETCH_COLUMN);
    check($left === ['rgpd3-recent'], 'Conservation : journal de 13 mois supprimé, 11 mois conservé');
    db()->exec("DELETE FROM logs WHERE message = 'rgpd3-recent'");

    [$code, , $loc] = http('admin/logout.php', ['csrf' => csrf('admin/index.php')]);
    check($code === 302 && str_contains($loc, 'login.php'), 'Déconnexion');
    [$code] = http('admin/index.php');
    check($code === 302, 'Session fermée après déconnexion');

    echo "\n== 1b. Mot de passe oublié ==\n";

    [$code, , $loc] = http('admin/forgot.php', ['csrf' => csrf('admin/forgot.php'), 'email' => $email]);
    check($code === 302 && str_contains($loc, 'reset.php'), 'Demande de code → page de vérification');
    $row = db()->query('SELECT * FROM password_resets ORDER BY id DESC LIMIT 1')->fetch();
    check($row && (int) $row['admin_id'] === (int) $admin['id'] && $row['expires_at'] - time() <= 300 && $row['expires_at'] - time() > 290, 'Code créé, valable 5 minutes');
    check(!preg_match('/^\d{8}$/', $row['code_hash']), 'Code stocké haché (pas en clair)');

    [$code, $body] = http('admin/forgot.php', ['csrf' => csrf('admin/forgot.php'), 'email' => 'inconnu@exemple.fr']);
    check($code === 200 && str_contains($body, 'patienter'), 'Nouvelle demande trop rapide refusée');
    clear_throttle();
    [$code, , $loc] = http('admin/forgot.php', ['csrf' => csrf('admin/forgot.php'), 'email' => 'inconnu@exemple.fr']);
    [, $body] = http('admin/reset.php');
    check($code === 302 && str_contains($body, 'Si cette adresse correspond'), 'Adresse inconnue : même réponse (pas d\'énumération)');

    // Code connu injecté pour tester la vérification
    $known = '12345678';
    db()->prepare('UPDATE password_resets SET used = 1')->execute();
    db()->prepare('INSERT INTO password_resets (admin_id, code_hash, expires_at) VALUES (?, ?, ?)')
        ->execute([$admin['id'], password_hash($known, PASSWORD_DEFAULT), time() + 300]);
    $form = fn (string $code, string $pw, string $confirm) => http('admin/reset.php', [
        'csrf' => csrf('admin/reset.php'), 'email' => $email, 'code' => $code, 'password' => $pw, 'password_confirm' => $confirm,
    ]);

    [, $body] = $form($known, 'court', 'court');
    check(str_contains($body, 'au moins 20 caractères'), 'Règle des 20 caractères appliquée');
    [, $body] = $form($known, NEW_PASSWORD, NEW_PASSWORD . 'x');
    check(str_contains($body, 'confirmation ne correspond pas'), 'Confirmation vérifiée');
    [, $body] = $form('00000000', NEW_PASSWORD, NEW_PASSWORD);
    check(str_contains($body, 'Code invalide') && str_contains($body, '3 s'), 'Mauvais code refusé avec délai');
    [, $body] = $form($known, NEW_PASSWORD, NEW_PASSWORD);
    check(str_contains($body, 'patienter'), 'Bon code refusé pendant le délai');
    clear_throttle();

    db()->exec('UPDATE password_resets SET expires_at = ' . (time() - 1) . ' WHERE used = 0');
    [, $body] = $form($known, NEW_PASSWORD, NEW_PASSWORD);
    check(str_contains($body, 'Code invalide'), 'Code expiré refusé');
    clear_throttle();

    db()->exec('UPDATE password_resets SET expires_at = ' . (time() + 300) . ' WHERE used = 0');
    [$code, , $loc] = $form($known, NEW_PASSWORD, NEW_PASSWORD);
    check($code === 302 && str_contains($loc, 'login.php'), 'Bon code → mot de passe changé');
    [$code] = $form($known, NEW_PASSWORD, NEW_PASSWORD);
    clear_throttle();
    check($code === 200, 'Code à usage unique');
    [$code] = login_as($email, NEW_PASSWORD);
    check($code === 302, 'Connexion avec le nouveau mot de passe');

    echo "\n== 2. Configuration e-mail (Mailjet) ==\n";

    $fakeSecret = 'FAUX-SECRET-' . bin2hex(random_bytes(4));
    $mailForm = [
        'transport' => 'smtp', 'from_name' => 'InfoLeader', 'from_email' => 'expediteur@exemple.fr',
        'to_email' => 'destinataire@exemple.fr', 'smtp_host' => 'in-v3.mailjet.com', 'smtp_port' => '587',
        'smtp_secure' => 'tls', 'smtp_user' => '0123456789abcdef0123456789abcdef', 'smtp_pass' => $fakeSecret, 'debug' => '1',
    ];
    [, $body] = http('admin/email.php', ['csrf' => csrf('admin/email.php'), 'from_email' => 'pas-un-email'] + $mailForm);
    check(str_contains($body, 'expéditeur invalide') || str_contains(http('admin/email.php')[1], 'expéditeur invalide'), 'Adresse expéditeur invalide refusée');

    @unlink(MAIL_DEBUG_LOG);
    [$code] = http('admin/email.php', ['csrf' => csrf('admin/email.php'), 'action' => 'test'] + $mailForm);
    [, $body] = http('admin/email.php');
    check($code === 302 && str_contains($body, 'Configuration enregistrée'), 'Configuration enregistrée');
    $mailjetUp = mailjet_up();
    if ($mailjetUp) {
        check(str_contains($body, 'Échec de l\'envoi') && str_contains($body, 'authenticate'), 'Test réel : Mailjet joint en STARTTLS, faux identifiants refusés');
    }
    check(!str_contains($body, $fakeSecret), 'Clé SMTP jamais réaffichée');
    $stored = db()->query("SELECT value FROM settings WHERE name = 'mail'")->fetchColumn();
    check(!str_contains($stored, $fakeSecret) && decrypt_secret(json_decode($stored, true)['smtp_pass']) === $fakeSecret, 'Clé SMTP chiffrée en base (et déchiffrable)');
    $debugLog = (string) @file_get_contents(MAIL_DEBUG_LOG);
    if ($mailjetUp) {
        check(str_contains($debugLog, 'STARTTLS') && str_contains($debugLog, '[masqué]'), 'Log de débogage écrit, AUTH masqué');
    } else {
        $skipped += 2;
        echo "  IGNORÉ Mailjet ne répond pas (réseau ou blocage temporaire) : 2 vérifications SMTP non faites\n";
    }
    check(!str_contains($debugLog, base64_encode($fakeSecret)) && !str_contains($debugLog, base64_encode('0123456789abcdef0123456789abcdef')), 'Aucun identifiant (même en base64) dans le log');
    $logs = db()->query('SELECT GROUP_CONCAT(message) FROM logs WHERE created_at >= NOW() - INTERVAL 5 MINUTE')->fetchColumn();
    check(!str_contains((string) $logs, $fakeSecret), 'Clé SMTP absente des journaux');

    [, $body] = http('admin/email.php', ['csrf' => csrf('admin/email.php'), 'action' => 'save', 'smtp_pass' => ''] + $mailForm);
    $stored2 = json_decode(db()->query("SELECT value FROM settings WHERE name = 'mail'")->fetchColumn(), true);
    check(decrypt_secret($stored2['smtp_pass']) === $fakeSecret, 'Champ vide : clé existante conservée');
    // Envoi de nouveau désactivé : la suite ne dépend plus du réseau ni de la lenteur de Mailjet
    set_setting('mail', ['from_email' => ''] + mail_config());

    echo "\n== 3. Modules Mot de passe & Utilisateur ==\n";

    $pwForm = fn (string $current, string $pw, string $confirm) => http('admin/password.php', [
        'csrf' => csrf('admin/password.php'), 'current' => $current, 'password' => $pw, 'password_confirm' => $confirm,
    ]);
    [, $body] = $pwForm('faux', TEST_PASSWORD, TEST_PASSWORD);
    check(str_contains($body, 'actuel est incorrect') && str_contains($body, '3 s'), 'Mot de passe actuel faux refusé (avec délai)');
    clear_throttle();
    [, $body] = $pwForm(NEW_PASSWORD, 'Court-1!', 'Court-1!');
    check(str_contains($body, 'au moins 20 caractères'), 'Moins de 20 caractères refusé');
    [, $body] = $pwForm(NEW_PASSWORD, 'tout-en-minuscules-1234', 'tout-en-minuscules-1234');
    check(str_contains($body, 'majuscule'), 'Règle majuscule appliquée');
    [, $body] = $pwForm(NEW_PASSWORD, 'SansCaractereSpecial12345', 'SansCaractereSpecial12345');
    check(str_contains($body, 'caractère spécial'), 'Règle caractère spécial appliquée');
    [, $body] = $pwForm(NEW_PASSWORD, TEST_PASSWORD, TEST_PASSWORD . 'x');
    check(str_contains($body, 'confirmation ne correspond pas'), 'Confirmation différente refusée');
    [, $body] = $pwForm(NEW_PASSWORD, NEW_PASSWORD, NEW_PASSWORD);
    check(str_contains($body, 'différent de l'), 'Nouveau = actuel refusé');
    [$code] = $pwForm(NEW_PASSWORD, TEST_PASSWORD, TEST_PASSWORD);
    $hash = db()->query('SELECT password_hash FROM admins WHERE id = ' . (int) $admin['id'])->fetchColumn();
    check($code === 302 && password_verify(TEST_PASSWORD, $hash) && str_starts_with($hash, '$2y$'), 'Mot de passe changé, stocké avec password_hash()');

    $userForm = fn (string $current, string $mail, string $confirm) => http('admin/user.php', [
        'csrf' => csrf('admin/user.php'), 'current' => $current, 'email' => $mail, 'email_confirm' => $confirm,
    ]);
    [, $body] = $userForm('faux', 'nouveau@exemple.fr', 'nouveau@exemple.fr');
    check(str_contains($body, 'actuel est incorrect'), 'E-mail : mot de passe actuel exigé');
    clear_throttle();
    [, $body] = $userForm(TEST_PASSWORD, 'pas-valide@', 'pas-valide@');
    check(str_contains($body, 'pas valide'), 'E-mail : syntaxe vérifiée');
    [, $body] = $userForm(TEST_PASSWORD, strtoupper($email), strtoupper($email));
    check(str_contains($body, 'identique'), 'E-mail : identique à l\'actuelle refusé');
    [, $body] = $userForm(TEST_PASSWORD, 'nouveau@exemple.fr', 'autre@exemple.fr');
    check(str_contains($body, 'ne correspondent pas'), 'E-mail : confirmation vérifiée');
    [$code] = $userForm(TEST_PASSWORD, 'nouveau@exemple.fr', 'nouveau@exemple.fr');
    [, $body] = http('admin/index.php');
    check($code === 302 && str_contains($body, 'nouveau@exemple.fr'), 'E-mail modifié et affiché au tableau de bord');
    [, , , , $raw] = http('admin/user.php', ['csrf' => csrf('admin/user.php'), 'current' => TEST_PASSWORD, 'email' => '<script>@x.fr', 'email_confirm' => '<script>@x.fr']);
    check(!str_contains($raw, '<script>@') && str_contains($raw, '&lt;script&gt;@'), 'Saisie HTML échappée à l\'affichage (XSS)');

    echo "\n== 4. Maintenance ==\n";

    [$code, $body] = http('admin/maintenance.php?tab=system');
    check($code === 200 && str_contains($body, 'État global') && str_contains($body, 'pdo_mysql'), 'Système : état global affiché');

    // Politique CSP envoyée sur le site public et dans l'admin (TODO_LIST.md, SEC-3)
    foreach (['index.php', 'products.php', 'contact.php', 'admin/login.php'] as $page) {
        [, , , $headers] = http($page);
        check(str_contains($headers, "Content-Security-Policy: default-src 'self'"), "CSP envoyée sur $page");
    }

    // Fichiers internes refusés par Apache (DATA-2 pour products.json)
    foreach (['products.json', 'composer.json', 'README.md'] as $file) {
        [$code] = http($file);
        check($code === 403, "Accès web refusé : $file (403)");
    }

    // Page « Mentions légales et confidentialité » (TODO_LIST.md, RGPD-2)
    [$code, $body] = http('mentions-legales.php');
    check($code === 200 && str_contains($body, 'entreprise fictive') && str_contains($body, 'INFOLEADER_SID'), 'Mentions légales : page affichée (entreprise fictive, cookie)');
    [, $body] = http('index.php');
    check(str_contains($body, 'href="mentions-legales.php"'), 'Mentions légales : lien dans le pied de page');

    // Contact → information RGPD, journal, puis filtres
    [, $body] = http('contact.php');
    check(str_contains($body, 'servent uniquement à répondre') && str_contains($body, 'faire supprimer'), 'Contact : information RGPD affichée sous le formulaire');
    $tag = 'test-' . bin2hex(random_bytes(3));
    [, $body] = http('contact.php', ['csrf' => csrf('contact.php'), 'nom' => 'Testeur', 'email' => 'testeur@exemple.fr', 'message' => "Bonjour $tag"]);
    check(str_contains($body, 'bien été reçu') && str_contains($body, 'testeur@exemple.fr'), 'Formulaire de contact : message reçu');
    $mailTry = db()->query("SELECT COUNT(*) FROM logs WHERE type = 'event' AND message LIKE 'Échec d''envoi d''e-mail à %' AND created_at >= NOW() - INTERVAL 10 SECOND")->fetchColumn();
    check($mailTry > 0, 'Contact : envoi e-mail tenté (désactivé pendant les tests, échec journalisé)');
    [, $body] = http('contact.php', ['csrf' => csrf('contact.php'), 'nom' => 'Spam', 'email' => 'spam@exemple.fr', 'message' => 'Encore']);
    check(str_contains($body, 'patienter') && !str_contains($body, 'bien été reçu'), 'Contact : envoi trop rapide refusé (anti-spam)');
    clear_throttle();
    [$code] = http('contact.php', ['nom' => 'x', 'email' => 'x@x.fr', 'message' => 'x']);
    check($code === 400, 'Contact : CSRF exigé');

    // Message long conservé en entier dans les journaux (DATA-1)
    $long = 'long-' . bin2hex(random_bytes(3)) . ' ' . str_repeat('é', 4000);
    http('contact.php', ['csrf' => csrf('contact.php'), 'nom' => 'Testeur', 'email' => 'testeur@exemple.fr', 'message' => $long]);
    $find = db()->prepare("SELECT message FROM logs WHERE type = 'contact' AND message LIKE ?");
    $find->execute(['%' . substr($long, 0, 11) . '%']);
    check(str_ends_with((string) $find->fetchColumn(), $long), 'Contact : message de 4000 caractères conservé en entier dans les journaux');
    db()->prepare("DELETE FROM logs WHERE type = 'contact' AND message LIKE ?")->execute(['%' . substr($long, 0, 11) . '%']);
    clear_throttle();
    [, $body] = http('admin/maintenance.php?tab=logs&type=contact&q=' . $tag);
    check(str_contains($body, "Bonjour $tag") && str_contains($body, '1 entrée(s)'), 'Journaux : message de contact retrouvé par filtre type + texte');
    [, $body] = http('admin/maintenance.php?tab=logs&type=login_ok&q=' . $tag);
    check(str_contains($body, '0 entrée(s)'), 'Journaux : filtre type exclut les autres entrées');
    [, $body] = http('admin/maintenance.php?tab=logs&type=blocked&from=' . date('Y-m-d') . '&to=' . date('Y-m-d'));
    $todayBlocked = (int) db()->query("SELECT COUNT(*) FROM logs WHERE type = 'blocked' AND DATE(created_at) = CURDATE()")->fetchColumn();
    check($todayBlocked > 0 && preg_match('/(\d+) entrée\(s\)/', $body, $m) && (int) $m[1] === $todayBlocked, 'Journaux : filtre par date + type blocage');

    $contactBackup = db()->query("SELECT * FROM logs WHERE type = 'contact'")->fetchAll();
    [, $body] = http('admin/maintenance.php', ['csrf' => csrf('admin/maintenance.php?tab=logs'), 'action' => 'purge_logs', 'type' => 'contact']);
    $left = (int) db()->query("SELECT COUNT(*) FROM logs WHERE type = 'contact'")->fetchColumn();
    check($left === count($contactBackup), 'Purge refusée sans confirmation');
    http('admin/maintenance.php', ['csrf' => csrf('admin/maintenance.php?tab=logs'), 'action' => 'purge_logs', 'type' => 'contact', 'confirm' => '1']);
    $left = (int) db()->query("SELECT COUNT(*) FROM logs WHERE type = 'contact'")->fetchColumn();
    $others = (int) db()->query("SELECT COUNT(*) FROM logs WHERE type <> 'contact'")->fetchColumn();
    check($left === 0 && $others > 0, 'Purge confirmée : seuls les messages de contact supprimés');
    $restore = db()->prepare('INSERT INTO logs (id, type, ip, message, created_at) VALUES (?, ?, ?, ?, ?)');
    foreach ($contactBackup as $row) {
        if (!str_contains($row['message'], $tag)) {
            $restore->execute(array_values($row));
        }
    }

    // Sauvegarde JSON
    [$code, , , $headers, $raw] = http('admin/maintenance.php', ['csrf' => csrf('admin/maintenance.php?tab=backup'), 'action' => 'backup']);
    $json = json_decode($raw, true);
    check($code === 200 && str_contains($headers, 'attachment') && isset($json['admins'], $json['logs'], $json['products']), 'Sauvegarde : JSON téléchargé');
    check(!str_contains($raw, $fakeSecret) && !str_contains($raw, '0123456789abcdef0123456789abcdef') && !str_contains($raw, '$2y$')
        && !str_contains($raw, config()['app_key']) && !str_contains($raw, 'smtp_pass') && !str_contains($raw, 'password_hash'), 'Sauvegarde : aucun mot de passe, hachage, secret SMTP ni clé');
    check(($json['settings']['mail']['smtp_host'] ?? '') === 'in-v3.mailjet.com', 'Sauvegarde : réglages non sensibles conservés');

    // Purge de storage/tmp
    @mkdir(STORAGE . '/tmp/sous-dossier');
    file_put_contents(STORAGE . '/tmp/a.tmp', 'x');
    file_put_contents(STORAGE . '/tmp/sous-dossier/b.tmp', 'y');
    file_put_contents(STORAGE . '/garde-moi.txt', 'hors de tmp');
    [, $body] = http('admin/maintenance.php?tab=tmp');
    check(str_contains($body, '2 fichier(s)'), 'storage/tmp : fichiers comptés');
    http('admin/maintenance.php', ['csrf' => csrf('admin/maintenance.php?tab=tmp'), 'action' => 'purge_tmp']);
    check(is_file(STORAGE . '/tmp/a.tmp'), 'Purge tmp refusée sans confirmation');
    http('admin/maintenance.php', ['csrf' => csrf('admin/maintenance.php?tab=tmp'), 'action' => 'purge_tmp', 'confirm' => '1']);
    [, $body] = http('admin/maintenance.php?tab=tmp');
    check(!file_exists(STORAGE . '/tmp/a.tmp') && !file_exists(STORAGE . '/tmp/sous-dossier') && str_contains($body, '0 fichier(s)'), 'Purge tmp : fichiers et sous-dossiers supprimés, état actualisé');
    check(is_file(STORAGE . '/garde-moi.txt') && is_file(STORAGE . '/config.php'), 'Aucun fichier supprimé hors de storage/tmp');
    @unlink(STORAGE . '/garde-moi.txt');

    echo "\n== 5. Articles ==\n";

    $articleName = 'Article test ' . $tag;
    $newId = fn () => (int) db()->query('SELECT MAX(id) FROM products')->fetchColumn();
    $article = fn (array $fields) => http('admin/products.php', ['csrf' => csrf('admin/products.php'), 'action' => 'save'] + $fields);

    [, $body] = $article(['name' => '', 'price' => 'abc']);
    check(str_contains($body, 'nom est requis') && str_contains($body, 'Prix invalide'), 'Ajout : nom et prix validés côté serveur');

    // Vraie image PNG 1x1
    $png = tempnam(sys_get_temp_dir(), 'il') . '.png';
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    [$code] = $article(['name' => $articleName, 'price' => '1 299,5', 'description' => '<b>gras</b> & "guillemets"', 'image' => new CURLFile($png, 'image/png', 'photo.png')]);
    $row = product($newId());
    check($code === 302 && $row['name'] === $articleName && $row['price'] === '1299.50', 'Ajout : article créé, prix « 1 299,5 » enregistré 1299.50');
    check($row['image'] && preg_match('#^assets/products/uploads/[a-f0-9]{24}\.png$#', $row['image']) && is_file(ROOT . '/' . $row['image']), 'Ajout : image enregistrée sous un nom aléatoire');

    [, , , , $raw] = http('products.php');
    check(str_contains($raw, e($articleName)) && str_contains($raw, "1\u{202F}299,50") && str_contains($raw, '&lt;b&gt;gras&lt;/b&gt;'), 'Page Produits : nouvel article affiché, prix formaté, HTML échappé');
    [, $body] = http('index.php');
    check(str_contains($body, $articleName), 'Accueil : article dans les nouveautés');

    // Faux fichier image (script PHP renommé en .jpg)
    $fake = tempnam(sys_get_temp_dir(), 'il') . '.jpg';
    file_put_contents($fake, '<?php echo "pirate"; ?>');
    [, $body] = $article(['id' => $row['id'], 'name' => $articleName, 'price' => '10', 'image' => new CURLFile($fake, 'image/jpeg', 'x.jpg')]);
    check(str_contains($body, 'Format refusé') && product($row['id'])['price'] === '1299.50', 'Faux fichier image refusé, article inchangé');
    file_put_contents(UPLOAD_DIR . '/test.php', '<?php echo "pirate";');
    [$code, $body] = http('assets/products/uploads/test.php');
    @unlink(UPLOAD_DIR . '/test.php');
    check($code === 403 && !str_contains($body, 'pirate'), 'Dossier uploads : script PHP jamais exécuté (403)');

    $oldImage = $row['image'];
    [$code] = $article(['id' => $row['id'], 'name' => $articleName . ' v2', 'price' => '49,90', 'description' => 'Modifié', 'remove_image' => '1']);
    $row = product($row['id']);
    check($code === 302 && $row['name'] === $articleName . ' v2' && $row['price'] === '49.90' && $row['image'] === null, 'Modification : nom, prix, image retirée');
    check(!is_file(ROOT . '/' . $oldImage), 'Ancienne image supprimée du disque');

    $originals = db()->query("SELECT COUNT(*) FROM products WHERE image LIKE 'assets/products/%.jpg'")->fetchColumn();
    [$code] = http('admin/products.php', ['csrf' => csrf('admin/products.php'), 'action' => 'delete', 'id' => $row['id']]);
    check($code === 302 && product($row['id']) === null, 'Suppression de l\'article');
    check(db()->query("SELECT COUNT(*) FROM products WHERE image LIKE 'assets/products/%.jpg'")->fetchColumn() === $originals, 'Articles d\'origine intacts');
    [$code] = http('admin/products.php', ['action' => 'delete', 'id' => 1]);
    check($code === 400, 'Suppression sans jeton CSRF refusée');
    @unlink($png);
    @unlink($fake);

    // Contrôle d'accès des modules après déconnexion
    http('admin/logout.php', ['csrf' => csrf('admin/index.php')]);
    foreach (['email.php', 'password.php', 'user.php', 'maintenance.php?tab=logs', 'products.php'] as $page) {
        [$code] = http("admin/$page");
        check($code === 302, "Module $page protégé (non connecté → redirection)");
    }
} finally {
    if ($mailBackup === false) {
        db()->exec("DELETE FROM settings WHERE name = 'mail'");
    } else {
        set_setting('mail', json_decode($mailBackup, true));
    }
    // Restauration du compte et nettoyage
    db()->prepare('UPDATE admins SET email = ?, password_hash = ? WHERE id = ?')
        ->execute([$admin['email'], $admin['password_hash'], $admin['id']]);
    db()->exec('UPDATE password_resets SET used = 1');
    clear_throttle();
    @unlink($jar);
}

echo "\nRésultat : $passed réussi(s), $failed échec(s)" . ($skipped ? ", $skipped ignorée(s)" : '') . "\n";
exit($failed > 0 ? 1 : 0);
