<?php
/* ============================================================
   Amorçage commun : configuration, base de données, session,
   CSRF, messages flash, journaux, limitation de débit,
   chiffrement des secrets et règles de mot de passe.
   Inclus en tête de chaque page dynamique.
   ============================================================ */

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('STORAGE', ROOT . '/storage');

require ROOT . '/vendor/autoload.php';

// Même fuseau que MySQL (heure locale) pour des dates cohérentes partout
date_default_timezone_set('Europe/Paris');

// Aucune erreur technique affichée au visiteur : elle part dans le journal PHP
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e): void {
    error_log('[InfoLeader] ' . $e);
    http_response_code(500);
    echo 'Erreur interne. Consultez le journal PHP.';
});

/* ---------- Configuration & base de données ---------- */

function config(): array
{
    static $config = null;
    if ($config === null) {
        $file = STORAGE . '/config.php';
        if (!is_file($file)) {
            http_response_code(503);
            exit('Application non installée : lancez « php app/install.php --email=... ».');
        }
        $config = require $file;
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // vraies requêtes préparées
            ]
        );
    }
    return $pdo;
}

/* ---------- Petites aides ---------- */

/** Échappement HTML */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** IP du client (REMOTE_ADDR uniquement : X-Forwarded-For est falsifiable) */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'cli';
}

/* ---------- Session sécurisée ---------- */

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('INFOLEADER_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    // Politique de sécurité stricte (site public + admin) : aucun script ni style en ligne
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
}

/* ---------- CSRF : vérifié automatiquement sur tout POST ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

if (PHP_SAPI !== 'cli' && is_post()) {
    $expected = $_SESSION['csrf'] ?? '';
    if ($expected === '' || !hash_equals($expected, (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    }
}

/* ---------- Messages flash (disparaissent après ~5,5 s) ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function render_flashes(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $message]) {
        $html .= '<div class="alert alert--' . e($type) . '" data-autohide role="status">' . e($message) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- Journaux ---------- */

/** Types : login_ok, login_fail, blocked, event, contact */
function log_event(string $type, string $message): void
{
    // 6000 caractères : un message de contact complet (5000 + nom + e-mail) tient dans la colonne TEXT
    db()->prepare('INSERT INTO logs (type, ip, message) VALUES (?, ?, ?)')
        ->execute([$type, client_ip(), mb_substr($message, 0, 6000)]);
}

/* ---------- Authentification ---------- */

const SESSION_IDLE = 1800; // déconnexion après 30 min d'inactivité

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_IDLE) {
        logout();
        return null;
    }
    $_SESSION['last_activity'] = time();
    $stmt = db()->prepare('SELECT id, email, password_hash FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    // Mot de passe changé ailleurs (autre session, réinitialisation) : cette session est fermée
    if (!$admin || !hash_equals($_SESSION['password_fp'] ?? '', password_fingerprint($admin['password_hash']))) {
        logout();
        return null;
    }
    unset($admin['password_hash']);
    return $admin;
}

/** Empreinte du hachage actuel, gardée en session pour détecter un changement de mot de passe */
function password_fingerprint(string $hash): string
{
    return hash('sha256', $hash);
}

/** Contrôle d'accès : à appeler en tête de chaque page protégée */
function require_login(): array
{
    $admin = current_admin();
    if (!$admin) {
        flash('error', 'Veuillez vous connecter.');
        redirect('login.php');
    }
    return $admin;
}

function login(array $admin): void
{
    session_regenerate_id(true); // empêche la fixation de session
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['password_fp'] = password_fingerprint($admin['password_hash']);
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

function logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/* ---------- Limitation de débit (anti force brute) ----------
   Par IP et par action, en base : résiste aux rechargements.
   Échec n°1 → 3 s d'attente, puis 6, 12, 24 s...
   Au 5e échec → IP bloquée 15 min pour cette action.          */

const THROTTLE_BASE_DELAY = 3;
const THROTTLE_MAX_DELAY = 60;
const THROTTLE_MAX_FAILURES = 5;
const THROTTLE_BLOCK_TIME = 900;
const THROTTLE_FORGET_AFTER = 3600; // les échecs anciens sont oubliés

function throttle_row(string $scope, bool $lock = false): array
{
    $stmt = db()->prepare('SELECT * FROM throttle WHERE ip = ? AND scope = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([client_ip(), $scope]);
    $row = $stmt->fetch();
    if (!$row || ($row['blocked_until'] < time() && $row['updated_at'] < time() - THROTTLE_FORGET_AFTER)) {
        return ['failures' => 0, 'next_allowed' => 0, 'blocked_until' => 0];
    }
    return $row;
}

/** Message d'attente si l'action est interdite pour l'instant, sinon null */
function throttle_check(string $scope): ?string
{
    $row = throttle_row($scope);
    $now = time();
    if ($row['blocked_until'] > $now) {
        return 'Trop de tentatives : votre adresse IP est bloquée jusqu\'à ' . date('H:i:s', (int) $row['blocked_until']) . '.';
    }
    if ($row['next_allowed'] > $now) {
        return 'Veuillez patienter ' . ($row['next_allowed'] - $now) . ' s avant une nouvelle tentative.';
    }
    return null;
}

/** Enregistre un échec ; renvoie le nombre de secondes à attendre */
function throttle_fail(string $scope): int
{
    // Transaction + verrou de ligne : deux échecs simultanés sont bien comptés deux fois
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT IGNORE INTO throttle (ip, scope) VALUES (?, ?)')->execute([client_ip(), $scope]);
        $wait = throttle_fail_locked($scope);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $wait;
}

/** Calcule et enregistre l'échec ; appelée dans la transaction de throttle_fail() */
function throttle_fail_locked(string $scope): int
{
    $row = throttle_row($scope, true);
    $now = time();
    // Un blocage expiré repart de zéro
    $failures = ($row['blocked_until'] > 0 && $row['blocked_until'] <= $now) ? 1 : $row['failures'] + 1;
    $blockedUntil = 0;
    if ($failures >= THROTTLE_MAX_FAILURES) {
        $blockedUntil = $now + THROTTLE_BLOCK_TIME;
        $wait = THROTTLE_BLOCK_TIME;
        log_event('blocked', "IP bloquée 15 min après $failures échecs (action : $scope)");
    } else {
        $wait = min(THROTTLE_BASE_DELAY * 2 ** ($failures - 1), THROTTLE_MAX_DELAY);
    }
    db()->prepare(
        'REPLACE INTO throttle (ip, scope, failures, next_allowed, blocked_until, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([client_ip(), $scope, $failures, $now + $wait, $blockedUntil, $now]);
    return $wait;
}

function throttle_reset(string $scope): void
{
    db()->prepare('DELETE FROM throttle WHERE ip = ? AND scope = ?')->execute([client_ip(), $scope]);
}

/** Message affiché après un échec */
function wait_message(int $wait): string
{
    return $wait >= THROTTLE_BLOCK_TIME
        ? 'Trop de tentatives : votre adresse IP est bloquée pendant 15 minutes.'
        : "Prochaine tentative possible dans $wait s.";
}

/* ---------- Règles de mot de passe ---------- */

const PASSWORD_RULES = [
    'Au moins 20 caractères (72 octets maximum)',
    'Au moins une lettre minuscule et une majuscule',
    'Au moins un chiffre',
    'Au moins un caractère spécial (ex. : ! ? @ # - _)',
];

/** Liste des règles non respectées (vide = mot de passe valide) */
function password_errors(string $password): array
{
    $errors = [];
    if (mb_strlen($password) < 20) {
        $errors[] = 'Le mot de passe doit contenir au moins 20 caractères.';
    }
    if (strlen($password) > 72) { // limite de l'algorithme bcrypt
        $errors[] = 'Le mot de passe ne doit pas dépasser 72 octets.';
    }
    if (!preg_match('/\p{Ll}/u', $password) || !preg_match('/\p{Lu}/u', $password)) {
        $errors[] = 'Le mot de passe doit contenir une minuscule et une majuscule.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }
    if (!preg_match('/[^\p{L}\d]/u', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
    }
    return $errors;
}

/* ---------- Réglages & chiffrement des secrets ---------- */

function get_setting(string $name, array $default = []): array
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE name = ?');
    $stmt->execute([$name]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (json_decode($value, true) ?: $default);
}

function set_setting(string $name, array $value): void
{
    db()->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)')
        ->execute([$name, json_encode($value, JSON_UNESCAPED_UNICODE)]);
}

/** AES-256-GCM avec la clé de storage/config.php (hors base de données) */
function encrypt_secret(string $plain): string
{
    $key = base64_decode(config()['app_key']);
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(string $encoded): ?string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }
    $key = base64_decode(config()['app_key']);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}
