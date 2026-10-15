<?php
/* ============================================================
   Module « Maintenance » : Système, Journaux, Sauvegarde,
   Purge de storage/tmp
   ============================================================ */

require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/layout.php';
require ROOT . '/app/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;

$admin = require_login();

const TMP_DIR = STORAGE . '/tmp';
const LOG_TYPES = [
    'login_ok' => 'Connexion réussie',
    'login_fail' => 'Échec',
    'blocked' => 'Blocage',
    'event' => 'Événement',
    'contact' => 'Message de contact',
];
const PROTECTED_TMP_FILES = ['.htaccess', '.gitkeep'];

/* ---------- Outils ---------- */

/** Fichiers de storage/tmp (sans suivre les liens) : [nombre, octets] */
function tmp_stats(): array
{
    $count = 0;
    $size = 0;
    if (is_dir(TMP_DIR)) {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TMP_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($items as $file) {
            if (!in_array($file->getFilename(), PROTECTED_TMP_FILES, true)) {
                $count++;
                $size += $file->getSize();
            }
        }
    }
    return [$count, $size];
}

/** Supprime le contenu de storage/tmp, jamais en dehors ; renvoie [supprimés, ignorés] */
function purge_tmp(): array
{
    $base = realpath(TMP_DIR);
    if ($base === false) {
        return [0, 0];
    }
    $deleted = 0;
    $skipped = 0;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST // fichiers avant leurs dossiers
    );
    foreach ($items as $item) {
        $path = $item->getPathname();
        $real = realpath($path);
        // Liens symboliques/jonctions ignorés ; tout chemin résolu hors de tmp est refusé
        if ($item->isLink() || $real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)
            || ($item->getPath() === $base && in_array($item->getFilename(), PROTECTED_TMP_FILES, true))) {
            $skipped++;
            continue;
        }
        $ok = $item->isDir() ? @rmdir($real) : @unlink($real);
        $ok ? $deleted++ : $skipped++;
    }
    return [$deleted, $skipped];
}

function human_size(int $bytes): string
{
    return $bytes < 1024 ? "$bytes o" : ($bytes < 1048576 ? round($bytes / 1024, 1) . ' Ko' : round($bytes / 1048576, 1) . ' Mo');
}

/** Retire récursivement toute clé sensible (mots de passe, secrets, jetons, clés) */
function strip_secrets(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_string($key) && preg_match('/pass|secret|token|api|key|hash|smtp_user/i', $key)) {
            unset($data[$key]);
        } elseif (is_array($value)) {
            $data[$key] = strip_secrets($value);
        }
    }
    return $data;
}

/** Contrôles de l'état du système : [libellé, ok|warn|ko, détail] */
function system_checks(): array
{
    $checks = [];
    $checks[] = ['Version de PHP', version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'ko', PHP_VERSION . ' (' . PHP_SAPI . ')'];
    foreach (['pdo_mysql', 'openssl', 'mbstring', 'json'] as $ext) {
        $checks[] = ["Extension $ext", extension_loaded($ext) ? 'ok' : 'ko', extension_loaded($ext) ? 'chargée' : 'manquante'];
    }
    try {
        $version = db()->query('SELECT VERSION()')->fetchColumn();
        $checks[] = ['Base de données', 'ok', 'MySQL ' . $version];
    } catch (Throwable $ex) {
        $checks[] = ['Base de données', 'ko', 'connexion impossible'];
    }
    $checks[] = ['PHPMailer', class_exists(PHPMailer::class) ? 'ok' : 'ko', class_exists(PHPMailer::class) ? PHPMailer::VERSION : 'lancer composer install'];
    $checks[] = ['Envoi d\'e-mails', mail_ready() ? 'ok' : 'warn', mail_ready() ? 'configuré (' . mail_config()['transport'] . ')' : 'non configuré'];

    foreach (['storage', 'storage/tmp', 'storage/logs', 'assets/products/uploads'] as $dir) {
        $writable = is_dir(ROOT . "/$dir") && is_writable(ROOT . "/$dir");
        $checks[] = ["Écriture dans $dir", $writable ? 'ok' : 'ko', $writable ? 'autorisée' : 'impossible'];
    }
    foreach (['storage/config.php' => 'Configuration (BDD + clé)', 'composer.json' => 'Dépendances Composer', 'vendor/autoload.php' => 'Autoload Composer'] as $file => $label) {
        $checks[] = ["$label — $file", is_file(ROOT . "/$file") ? 'ok' : 'ko', is_file(ROOT . "/$file") ? 'présent' : 'absent'];
    }
    foreach (['app', 'storage', 'vendor', 'tests'] as $dir) {
        $ok = is_file(ROOT . "/$dir/.htaccess");
        $checks[] = ["Accès web bloqué : $dir/", $ok ? 'ok' : 'ko', $ok ? '.htaccess présent' : '.htaccess manquant'];
    }
    $checks[] = ['Cookie de session HttpOnly', ini_get('session.cookie_httponly') || session_get_cookie_params()['httponly'] ? 'ok' : 'warn', 'SameSite=' . session_get_cookie_params()['samesite']];
    $checks[] = ['HTTPS', !empty($_SERVER['HTTPS']) ? 'ok' : 'warn', !empty($_SERVER['HTTPS']) ? 'actif' : 'non (normal en local, obligatoire en production)'];
    [$count, $size] = tmp_stats();
    $checks[] = ['Fichiers temporaires (storage/tmp)', $count > 0 ? 'warn' : 'ok', "$count fichier(s), " . human_size($size)];
    $debugSize = is_file(MAIL_DEBUG_LOG) ? filesize(MAIL_DEBUG_LOG) : 0;
    $checks[] = ['Log de débogage SMTP', 'ok', $debugSize ? human_size($debugSize) : 'vide'];
    return $checks;
}

/* ---------- Actions (POST + CSRF + confirmation) ---------- */

if (is_post()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'purge_logs') {
        $type = (string) ($_POST['type'] ?? '');
        if (empty($_POST['confirm'])) {
            flash('error', 'Cochez la case de confirmation pour purger les journaux.');
        } elseif ($type !== '' && !isset(LOG_TYPES[$type])) {
            flash('error', 'Type de journal inconnu.');
        } else {
            $stmt = $type === '' ? db()->prepare('DELETE FROM logs') : db()->prepare('DELETE FROM logs WHERE type = ?');
            $stmt->execute($type === '' ? [] : [$type]);
            $label = $type === '' ? 'tous types' : LOG_TYPES[$type];
            log_event('event', "Journaux purgés ($label) : {$stmt->rowCount()} ligne(s) par {$admin['email']}");
            flash('success', "{$stmt->rowCount()} entrée(s) supprimée(s) ($label).");
        }
        redirect('maintenance.php?tab=logs');
    }

    if ($action === 'purge_tmp') {
        if (empty($_POST['confirm'])) {
            flash('error', 'Cochez la case de confirmation pour purger storage/tmp.');
        } else {
            [$deleted, $skipped] = purge_tmp();
            log_event('event', "Purge de storage/tmp : $deleted élément(s) supprimé(s), $skipped ignoré(s)");
            flash('success', "$deleted élément(s) supprimé(s)" . ($skipped ? ", $skipped ignoré(s) (protégés ou hors du dossier)" : '') . '.');
        }
        redirect('maintenance.php?tab=tmp');
    }

    if ($action === 'purge_smtp_log') {
        if (empty($_POST['confirm'])) {
            flash('error', 'Cochez la case de confirmation pour vider le log SMTP.');
        } else {
            @unlink(MAIL_DEBUG_LOG);
            log_event('event', 'Log de débogage SMTP vidé par ' . $admin['email']);
            flash('success', 'Log de débogage SMTP vidé.');
        }
        redirect('maintenance.php?tab=tmp');
    }

    if ($action === 'backup') {
        $backup = [
            'application' => 'InfoLeader',
            'generated_at' => date('c'),
            'note' => 'Sauvegarde sans mots de passe, secrets SMTP ni jetons.',
            'admins' => db()->query('SELECT id, email, created_at, updated_at FROM admins')->fetchAll(),
            'settings' => [],
            // Option RGPD : sans les messages de contact (données personnelles des visiteurs)
            'logs' => db()->query('SELECT id, type, ip, message, created_at FROM logs'
                . (empty($_POST['exclude_contact']) ? '' : " WHERE type <> 'contact'") . ' ORDER BY id')->fetchAll(),
            'products' => db()->query('SELECT id, name, price, description, image, created_at, updated_at FROM products ORDER BY id')->fetchAll(),
        ];
        foreach (db()->query('SELECT name, value FROM settings')->fetchAll() as $row) {
            $backup['settings'][$row['name']] = json_decode($row['value'], true);
        }
        $backup = strip_secrets($backup);
        log_event('event', 'Sauvegarde JSON exportée par ' . $admin['email']);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="infoleader-sauvegarde-' . date('Ymd-His') . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/* ---------- Affichage ---------- */

$tabs = ['system' => 'Système', 'logs' => 'Journaux', 'backup' => 'Sauvegarde', 'tmp' => 'Fichiers temporaires'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'system';

admin_header('Maintenance', $admin, 'État du système, journaux, sauvegarde et fichiers temporaires.');
?>
<nav class="tabs" aria-label="Outils de maintenance">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="maintenance.php?tab=<?= $key ?>"<?= $key === $tab ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'system'): ?>
    <?php
    $checks = system_checks();
    $statuses = array_column($checks, 1);
    $global = in_array('ko', $statuses, true) ? 'ko' : (in_array('warn', $statuses, true) ? 'warn' : 'ok');
    $globalLabel = ['ok' => 'Tout est opérationnel', 'warn' => 'Opérationnel, avec des points d\'attention', 'ko' => 'Problème détecté'][$global];
    ?>
    <section class="panel">
        <h2>État global : <span class="badge badge--<?= $global ?>"><?= e($globalLabel) ?></span></h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Contrôle</th><th>État</th><th>Détail</th></tr></thead>
                <tbody>
                <?php foreach ($checks as [$label, $status, $detail]): ?>
                    <tr>
                        <td><?= e($label) ?></td>
                        <td><span class="badge badge--<?= $status ?>"><?= ['ok' => 'OK', 'warn' => 'Attention', 'ko' => 'Erreur'][$status] ?></span></td>
                        <td><?= e($detail) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php elseif ($tab === 'logs'): ?>
    <?php
    // Filtres (GET) → requête préparée
    $filters = [
        'type' => isset(LOG_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '',
        'ip' => trim((string) ($_GET['ip'] ?? '')),
        'q' => trim((string) ($_GET['q'] ?? '')),
        'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
        'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
    ];
    $where = [];
    $params = [];
    if ($filters['type'] !== '') { $where[] = 'type = ?'; $params[] = $filters['type']; }
    if ($filters['ip'] !== '') { $where[] = 'ip = ?'; $params[] = $filters['ip']; }
    if ($filters['q'] !== '') { $where[] = 'message LIKE ?'; $params[] = '%' . addcslashes($filters['q'], '%_\\') . '%'; }
    if ($filters['from'] !== '') { $where[] = 'created_at >= ?'; $params[] = $filters['from'] . ' 00:00:00'; }
    if ($filters['to'] !== '') { $where[] = 'created_at <= ?'; $params[] = $filters['to'] . ' 23:59:59'; }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $count = db()->prepare("SELECT COUNT(*) FROM logs$sqlWhere");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $stmt = db()->prepare("SELECT * FROM logs$sqlWhere ORDER BY id DESC LIMIT 200");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    ?>
    <section class="panel">
        <h2>Filtres</h2>
        <form action="maintenance.php" method="get" class="filters">
            <input type="hidden" name="tab" value="logs">
            <div class="form-control">
                <label for="f-type">Type</label>
                <select id="f-type" name="type">
                    <option value="">Tous</option>
                    <?php foreach (LOG_TYPES as $key => $label): ?>
                        <option value="<?= $key ?>"<?= $filters['type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-control">
                <label for="f-ip">IP</label>
                <input type="text" id="f-ip" name="ip" value="<?= e($filters['ip']) ?>">
            </div>
            <div class="form-control">
                <label for="f-q">Texte</label>
                <input type="search" id="f-q" name="q" value="<?= e($filters['q']) ?>">
            </div>
            <div class="form-control">
                <label for="f-from">Du</label>
                <input type="date" id="f-from" name="from" value="<?= e($filters['from']) ?>">
            </div>
            <div class="form-control">
                <label for="f-to">Au</label>
                <input type="date" id="f-to" name="to" value="<?= e($filters['to']) ?>">
            </div>
            <button type="submit" class="btn">Filtrer</button>
            <a href="maintenance.php?tab=logs" class="btn btn--light">Réinitialiser</a>
        </form>
    </section>

    <section class="panel">
        <h2><?= $total ?> entrée(s)<?= $total > 200 ? ' — 200 plus récentes affichées' : '' ?></h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Type</th><th>IP</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['created_at']) ?></td>
                        <td><span class="badge badge--<?= ['login_ok' => 'ok', 'login_fail' => 'warn', 'blocked' => 'ko'][$row['type']] ?? '' ?>"><?= e(LOG_TYPES[$row['type']]) ?></span></td>
                        <td><?= e($row['ip']) ?></td>
                        <td class="wrap"><?= nl2br(e($row['message'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="4">Aucune entrée.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Purger les journaux</h2>
        <form action="maintenance.php" method="post" data-confirm="Supprimer définitivement ces journaux ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="purge_logs">
            <div class="form-control">
                <label for="p-type">Journaux à supprimer</label>
                <select id="p-type" name="type">
                    <option value="">Tous les journaux</option>
                    <?php foreach (LOG_TYPES as $key => $label): ?>
                        <option value="<?= $key ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check">
                <label><input type="checkbox" name="confirm" value="1" required> Je confirme la suppression définitive</label>
            </div>
            <button type="submit" class="btn btn--danger">Purger</button>
        </form>
    </section>

<?php elseif ($tab === 'backup'): ?>
    <section class="panel">
        <h2>Export JSON</h2>
        <p>Contient : comptes (adresse e-mail uniquement), réglages, journaux et produits.</p>
        <p class="muted">Exclus : mots de passe et leurs hachages, identifiant et clé SMTP, codes de réinitialisation, jetons, clé de chiffrement.</p>
        <form action="maintenance.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="backup">
            <div class="form-check">
                <label><input type="checkbox" name="exclude_contact" value="1" checked> Exclure les messages de contact (données personnelles des visiteurs)</label>
            </div>
            <button type="submit" class="btn">Télécharger la sauvegarde</button>
        </form>
    </section>

<?php else: ?>
    <?php [$count, $size] = tmp_stats(); ?>
    <section class="panel">
        <h2>storage/tmp : <?= $count ?> fichier(s), <?= e(human_size($size)) ?></h2>
        <p class="muted">Seul le contenu de storage/tmp est supprimé. Les liens et tout chemin sortant du dossier sont ignorés.</p>
        <form action="maintenance.php" method="post" data-confirm="Supprimer définitivement les fichiers temporaires ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="purge_tmp">
            <div class="form-check">
                <label><input type="checkbox" name="confirm" value="1" required> Je confirme la suppression des fichiers temporaires</label>
            </div>
            <button type="submit" class="btn btn--danger"<?= $count === 0 ? ' disabled' : '' ?>>Purger storage/tmp</button>
        </form>
    </section>

    <?php $smtpLog = is_file(MAIL_DEBUG_LOG) ? filesize(MAIL_DEBUG_LOG) : 0; ?>
    <section class="panel">
        <h2>Log de débogage SMTP : <?= $smtpLog ? e(human_size($smtpLog)) : 'vide' ?></h2>
        <p class="muted">storage/logs/smtp-debug.log grossit à chaque envoi tant que le débogage est activé (module E-mails).</p>
        <form action="maintenance.php" method="post" data-confirm="Vider le log de débogage SMTP ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="purge_smtp_log">
            <div class="form-check">
                <label><input type="checkbox" name="confirm" value="1" required> Je confirme la suppression du log SMTP</label>
            </div>
            <button type="submit" class="btn btn--danger"<?= $smtpLog === 0 ? ' disabled' : '' ?>>Vider le log SMTP</button>
        </form>
    </section>
<?php endif; ?>
<?php admin_footer();
