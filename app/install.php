<?php
/* ============================================================
   Installation (ligne de commande uniquement)
   - crée la base `infoleader` et un utilisateur MySQL dédié
     aux droits limités (SELECT/INSERT/UPDATE/DELETE) ;
   - applique app/schema.sql ;
   - écrit storage/config.php (accès BDD + clé de chiffrement) ;
   - crée le premier compte administrateur s'il n'existe pas.

   Usage :
   php app/install.php --email=admin@exemple.fr [--root-user=root] [--root-pass=]
   ============================================================ */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$opts = getopt('', ['email:', 'root-user::', 'root-pass::', 'host::', 'port::']);
$host = $opts['host'] ?? 'localhost';
$port = (int) ($opts['port'] ?? 3306);
$rootUser = $opts['root-user'] ?? 'root';
$rootPass = $opts['root-pass'] ?? '';

$root = dirname(__DIR__);
$configFile = $root . '/storage/config.php';

// Dossiers de stockage (protégés par storage/.htaccess)
foreach (['storage/tmp', 'storage/logs'] as $dir) {
    if (!is_dir("$root/$dir")) {
        mkdir("$root/$dir", 0770, true);
    }
}

// Configuration : réutilisée si elle existe déjà (installation relançable)
if (is_file($configFile)) {
    $config = require $configFile;
    echo "storage/config.php existant : réutilisé.\n";
} else {
    $config = [
        'db' => [
            'host' => $host,
            'port' => $port,
            'name' => 'infoleader',
            'user' => 'infoleader',
            'pass' => bin2hex(random_bytes(16)),
        ],
        // Clé AES-256 servant à chiffrer le mot de passe SMTP en base
        'app_key' => base64_encode(random_bytes(32)),
    ];
    file_put_contents($configFile, "<?php\n// Fichier généré par app/install.php — NE PAS publier.\nreturn " . var_export($config, true) . ";\n");
    echo "storage/config.php créé.\n";
}
$db = $config['db'];

// Connexion en root pour créer la base et l'utilisateur dédié
$pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $rootUser, $rootPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$quotedPass = $pdo->quote($db['pass']);
$pdo->exec("CREATE USER IF NOT EXISTS '{$db['user']}'@'localhost' IDENTIFIED BY $quotedPass");
$pdo->exec("ALTER USER '{$db['user']}'@'localhost' IDENTIFIED BY $quotedPass");
$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `{$db['name']}`.* TO '{$db['user']}'@'localhost'");
$pdo->exec("USE `{$db['name']}`");

// Application du schéma (instructions séparées par « ; »)
$sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/schema.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}
echo "Schéma appliqué.\n";

// Articles initiaux : repris de products.json (catalogue du TP1) si la table est vide
if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
    $seed = json_decode((string) file_get_contents($root . '/products.json'), true)['products'] ?? [];
    $stmt = $pdo->prepare('INSERT INTO products (name, price, description, image) VALUES (?, ?, ?, ?)');
    foreach ($seed as $p) {
        $stmt->execute([$p['name'], $p['price'], $p['description'], $p['image'] ?? null]);
    }
    echo count($seed) . " article(s) importé(s) depuis products.json.\n";
}

// Premier administrateur
if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
    $email = $opts['email'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        exit("Aucun administrateur : relancer avec --email=adresse@valide\n");
    }
    // Mot de passe aléatoire respectant les règles (20+ caractères, 4 classes)
    $password = 'Aa1!' . bin2hex(random_bytes(12));
    $stmt = $pdo->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    echo "\nAdministrateur créé : $email\nMot de passe initial (affiché une seule fois) : $password\n";
    echo "Changez-le dans le module « Mot de passe ».\n";
} else {
    echo "Un administrateur existe déjà : aucun compte créé.\n";
}
