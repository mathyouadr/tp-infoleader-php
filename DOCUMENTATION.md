# InfoLeader — Documentation technique

*Analyse du code réel du dépôt, réalisée le 15/10/2026 (TP 2bis).*
*Chaque affirmation renvoie au fichier qui la justifie. Quand une information n'est pas dans le code, c'est indiqué.*

## Table des matières

1. [Présentation générale du site](#1-présentation-générale-du-site)
2. [Stack technique](#2-stack-technique)
3. [Arborescence du projet](#3-arborescence-du-projet)
4. [Points d'entrée et routage](#4-points-dentrée-et-routage)
5. [Stockage des données](#5-stockage-des-données)
6. [Authentification, sessions et sécurité](#6-authentification-sessions-et-sécurité)
7. [Front-end](#7-front-end)
8. [Intégrations externes](#8-intégrations-externes)
9. [Configuration](#9-configuration)
10. [Limites connues et risques](#10-limites-connues-et-risques)

---

## 1. Présentation générale du site

InfoLeader est le site vitrine d'une entreprise **fictive** de matériel informatique. C'est un projet scolaire (TP1 + TP2) qui tourne en local sous WAMP (`README.md`).

Le site comporte deux parties.

**Site public** (TP1)

| Page | Fichier | Fonction |
|---|---|---|
| Accueil | `index.php` | Bannière, encart « Nouveautés » (3 derniers articles), section « Qui sommes-nous ? » |
| Nos Produits | `products.php` | Grille de tous les articles + recherche en temps réel |
| Contact | `contact.php` | Formulaire : le message est journalisé puis envoyé par e-mail |
| Mentions légales | `mentions-legales.php` | Éditeur (entreprise fictive), hébergement local, données personnelles, cookie (lien dans le pied de page) |

**Administration** (TP2), sous `admin/`

| Écran | Fichier | Fonction |
|---|---|---|
| Connexion | `admin/login.php` | Accès réservé, protection contre la force brute |
| Mot de passe oublié | `admin/forgot.php`, `admin/reset.php` | Code à 8 chiffres envoyé par e-mail, valable 5 min |
| Tableau de bord | `admin/index.php` | Compte connecté, connexion précédente, accès aux modules |
| Articles | `admin/products.php` | Ajouter, modifier, supprimer un article (avec image) |
| E-mails | `admin/email.php` | Configurer Mailjet SMTP ou PHP `mail()`, envoyer un test |
| Mot de passe | `admin/password.php` | Changer le mot de passe |
| Utilisateur | `admin/user.php` | Changer l'adresse e-mail du compte |
| Maintenance | `admin/maintenance.php` | État du système, journaux, sauvegarde JSON, purge de `storage/tmp` |
| Déconnexion | `admin/logout.php` | POST uniquement |

Il n'y a **qu'un seul compte administrateur** et aucun compte visiteur (`app/install.php` crée un admin seulement si la table est vide). Le site n'a ni panier, ni commande, ni paiement.

---

## 2. Stack technique

| Couche | Technologie | Source |
|---|---|---|
| Serveur | WAMP sous Windows : Apache 2.4.65 | dossier `C:\wamp\bin\apache\apache2.4.65` (hors code) |
| Langage | PHP 8.3.28, sans framework, `declare(strict_types=1)` | `app/bootstrap.php`, dossier `C:\wamp\bin\php\php8.3.28` |
| Version minimale | PHP 8.1 (contrôle affiché dans Maintenance) | `admin/maintenance.php` (`system_checks()`) |
| Base de données | MySQL 8.4.7, base `infoleader` | `app/install.php`, dossier `C:\wamp\bin\mysql\mysql8.4.7` |
| Accès BDD | PDO, requêtes préparées, émulation désactivée | `app/bootstrap.php` (`db()`) |
| E-mails | PHPMailer v6.12.0 + Mailjet SMTP | `composer.lock`, `app/mailer.php` |
| Dépendances | Composer : une seule (`phpmailer/phpmailer` ^6.9) | `composer.json` |
| Extensions PHP | `pdo_mysql`, `openssl`, `mbstring`, `json` (contrôlées), `fileinfo` (uploads), `curl` (tests) | `admin/maintenance.php`, `app/catalog.php`, `tests/http_test.php` |
| Front | HTML5, un seul CSS (`style.css`), un seul JS vanilla (`script.js`) | `app/site.php`, `app/layout.php` |
| Police | Pile système sans-serif (`Segoe UI Variable`, `system-ui`) | `style.css` (`--font`) |
| Tests | Script PHP + cURL contre Apache : 98 vérifications `check()` | `tests/http_test.php` |

Aucune ressource externe (CDN, police web, script tiers) n'est chargée : aucune URL externe dans `style.css`, `app/site.php` ou `app/layout.php`.

---

## 3. Arborescence du projet

```
infoleader/
├── index.php              Accueil (bannière, nouveautés, présentation)
├── products.php           Catalogue + recherche en temps réel
├── contact.php            Formulaire de contact (journal + e-mail)
├── mentions-legales.php   Mentions légales et confidentialité (RGPD)
├── style.css              Feuille de style unique (site public ET admin)
├── script.js              Script unique : menu, filtre, messages, confirmations
├── products.json          Données initiales du catalogue (lu seulement par l'installeur)
├── composer.json / .lock  Dépendance PHPMailer
├── .htaccess              Pas de listing ; refuse composer.*, *.md, .gitignore
├── admin/                 Administration (10 pages, voir section 1)
├── app/                   Code partagé — accès web refusé (app/.htaccess)
│   ├── bootstrap.php      Config, BDD, session, CSRF, flash, journaux, auth, anti force brute,
│   │                      règles de mot de passe, réglages, chiffrement
│   ├── layout.php         En-tête / pied de page de l'admin
│   ├── site.php           En-tête / pied de page du site public + carte produit
│   ├── catalog.php        Lecture des articles, format des prix, envoi/suppression d'images
│   ├── mailer.php         Configuration et envoi des e-mails, log SMTP masqué
│   ├── install.php        Installeur en ligne de commande
│   └── schema.sql         Schéma des 6 tables
├── storage/               Données locales — accès web refusé (storage/.htaccess)
│   ├── config.php         Généré : accès BDD + clé de chiffrement (non versionné)
│   ├── tmp/               Fichiers temporaires (purgeables depuis Maintenance)
│   └── logs/              smtp-debug.log (si le débogage SMTP est activé)
├── assets/
│   ├── banniere.png       Image de la bannière d'accueil
│   └── products/          6 images du TP1 + uploads/ (images envoyées, scripts refusés)
├── tests/http_test.php    Tests de bout en bout — accès web refusé (tests/.htaccess)
├── vendor/                PHPMailer (Composer, non versionné) — accès web refusé
├── README.md              Installation et configuration Mailjet
├── ETAT-DU-PROJET.md      État d'avancement au 13/10/2026
└── AGENTS.md              Règles pour les agents IA qui modifient le projet
```

Ce que Git ne versionne pas (`.gitignore`) : `storage/config.php`, le contenu de `storage/tmp/` et `storage/logs/`, les images envoyées, `vendor/`.

---

## 4. Points d'entrée et routage

### Pas de routeur

Le site n'a **pas de contrôleur frontal** ni de réécriture d'URL. Apache sert directement le fichier PHP demandé : une URL = un fichier.

| URL locale | Fichier exécuté |
|---|---|
| `http://localhost/infoleader/` | `index.php` (via `DirectoryIndex` d'Apache) |
| `…/products.php`, `…/contact.php` | fichier du même nom |
| `…/admin/` | `admin/index.php` → redirige vers `login.php` si non connecté |
| `…/admin/products.php?edit=12` | formulaire pré-rempli pour l'article 12 |
| `…/admin/maintenance.php?tab=logs&type=contact&q=…&from=…&to=…` | onglet Journaux filtré |

Les paramètres `GET` sont validés : `tab` doit appartenir à une liste fixe, `type` à `LOG_TYPES`, les dates au format `AAAA-MM-JJ` (`admin/maintenance.php`).

### Déroulement d'une page

Toutes les pages suivent le même schéma (ex. `admin/password.php`) :

1. `require app/bootstrap.php` : démarre la session, envoie les en-têtes de sécurité, **refuse tout POST sans jeton CSRF valide** (code 400).
2. `require app/layout.php` (admin) ou `app/site.php` (public).
3. `$admin = require_login();` sur les pages protégées : sinon redirection vers `login.php`.
4. Si la requête est un POST : validation, action en base, message `flash()`, puis `redirect()` (schéma PRG : Post → Redirect → Get, qui évite le double envoi au rechargement).
5. Affichage : `admin_header()` / `site_header()`, contenu, `admin_footer()` / `site_footer()`.

Pages admin accessibles sans connexion : `login.php`, `forgot.php`, `reset.php` (et `logout.php`, qui ne fait rien sans POST).

### Accès bloqués par Apache

| Règle | Fichier |
|---|---|
| `Require all denied` sur `app/`, `storage/`, `tests/`, `vendor/` | `.htaccess` de chaque dossier |
| Refus de `composer.json`, `composer.lock`, `*.md`, `.gitignore` ; `Options -Indexes` | `.htaccess` (racine) |
| Aucun script exécutable dans les images envoyées (`php`, `phtml`, `phar`, `html`, `svg`…) | `assets/products/uploads/.htaccess` |
| `install.php` répond 404 s'il est appelé hors ligne de commande | `app/install.php` |

### Erreurs

- `storage/config.php` absent → code 503 « Application non installée » (`config()` dans `app/bootstrap.php`).
- Exception non gérée → code 500, message générique ; le détail part dans le journal PHP (`set_exception_handler`, `app/bootstrap.php`). Sous WAMP, ce journal est `C:/wamp/logs/php_error.log` (`phpForApache.ini`, hors code).

---

## 5. Stockage des données

Le site stocke ses données dans une **base MySQL**. Les fichiers JSON ne servent qu'à l'import initial, à un réglage et à l'export de sauvegarde.

### 5.1 Base MySQL `infoleader` (`app/schema.sql`)

Moteur InnoDB, encodage `utf8mb4`. Les tables sont créées par `app/install.php` (`CREATE TABLE IF NOT EXISTS`).

**`admins`** — compte administrateur

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED, clé primaire | |
| `email` | VARCHAR(254), UNIQUE | identifiant de connexion, reçoit les codes |
| `password_hash` | VARCHAR(255) | résultat de `password_hash()` |
| `created_at`, `updated_at` | DATETIME | `updated_at` mis à jour automatiquement |

**`password_resets`** — codes « mot de passe oublié »

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED, clé primaire | |
| `admin_id` | INT UNSIGNED, **clé étrangère → `admins.id`** (`ON DELETE CASCADE`) | |
| `code_hash` | VARCHAR(255) | code haché avec `password_hash()` |
| `expires_at` | INT UNSIGNED | timestamp Unix (création + 300 s) |
| `attempts` | TINYINT UNSIGNED | essais faux sur ce code |
| `used` | TINYINT(1) | 1 = utilisé ou invalidé |
| `created_at` | DATETIME | |

**`throttle`** — limitation des tentatives

| Colonne | Type | Rôle |
|---|---|---|
| `ip` + `scope` | VARCHAR(45) + VARCHAR(20), **clé primaire composée** | une ligne par IP et par action |
| `failures` | INT UNSIGNED | nombre d'échecs |
| `next_allowed`, `blocked_until`, `updated_at` | INT UNSIGNED | timestamps Unix |

**`settings`** — réglages clé/valeur

| Colonne | Type | Rôle |
|---|---|---|
| `name` | VARCHAR(50), clé primaire | seule clé utilisée : `mail` |
| `value` | TEXT | **JSON** (configuration e-mail ; `smtp_pass` chiffré) |

**`logs`** — journaux

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED, clé primaire | |
| `type` | ENUM `login_ok`, `login_fail`, `blocked`, `event`, `contact` | indexé |
| `ip` | VARCHAR(45) | `REMOTE_ADDR` |
| `message` | TEXT (tronqué à 6000 caractères par `log_event()`) | les messages de contact y sont stockés |
| `created_at` | DATETIME | indexé |

**`products`** — catalogue

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED, clé primaire | l'ordre décroissant donne les « Nouveautés » |
| `name` | VARCHAR(120) | |
| `price` | DECIMAL(10,2) | |
| `description` | VARCHAR(500) | |
| `image` | VARCHAR(255), NULL | chemin relatif (`assets/products/…`) |
| `created_at`, `updated_at` | DATETIME | |

**Relations** : une seule clé étrangère (`password_resets.admin_id` → `admins.id`). Les autres tables sont indépendantes.

**Dates** : deux formats coexistent. `DATETIME` (heure locale MySQL) pour `created_at`, timestamps Unix (`INT`) pour les délais (`throttle`, `password_resets.expires_at`). PHP est forcé sur `Europe/Paris` pour rester aligné sur MySQL (`app/bootstrap.php`).

### 5.2 Principales requêtes

Toute requête qui contient une saisie passe par `db()->prepare(...)->execute([...])`. Les seules requêtes directes (`query()`) sont fixes : `products()` (avec `LIMIT (int)`), le `COUNT(*)` d'`index.php`, `SELECT VERSION()` et les `SELECT` de la sauvegarde.

| Fichier | Requêtes |
|---|---|
| `app/bootstrap.php` | `INSERT INTO logs` ; `SELECT id, email FROM admins WHERE id = ?` ; `SELECT/REPLACE/DELETE` sur `throttle` ; `SELECT/REPLACE` sur `settings` |
| `app/catalog.php` | `SELECT * FROM products ORDER BY id DESC [LIMIT n]` ; `SELECT * FROM products WHERE id = ?` |
| `admin/login.php` | `SELECT id, email, password_hash FROM admins WHERE email = ?` |
| `admin/forgot.php` | invalidation des anciens codes (`UPDATE password_resets SET used = 1`) puis `INSERT` du nouveau |
| `admin/reset.php` | jointure `password_resets` ⨝ `admins` sur le dernier code valide ; `UPDATE admins SET password_hash` ; compteur d'essais |
| `admin/products.php` | `INSERT`, `UPDATE`, `DELETE` sur `products` |
| `admin/maintenance.php` | journaux filtrés (clause `WHERE` construite avec des `?`, `LIKE` échappé) ; `DELETE FROM logs [WHERE type = ?]` ; export complet |
| `admin/index.php` | avant-dernière connexion réussie (`LIMIT 1 OFFSET 1`) |

### 5.3 Droits d'accès à la base

`app/install.php` crée l'utilisateur MySQL `infoleader@localhost` avec **seulement** `SELECT, INSERT, UPDATE, DELETE` sur la base. Le site ne peut donc pas modifier la structure. L'installeur, lui, se connecte en `root` (mot de passe vide par défaut, option `--root-pass`).

### 5.4 Fichiers

| Fichier | Format | Rôle |
|---|---|---|
| `products.json` | JSON | 6 articles du TP1, importés par `install.php` **seulement si `products` est vide**. Plus lu par les pages. |
| `storage/config.php` | tableau PHP | accès BDD + `app_key` (généré, non versionné) |
| `storage/logs/smtp-debug.log` | texte | dialogue SMTP masqué, si le débogage est activé |
| `storage/tmp/` | divers | fichiers temporaires (le code actuel n'y écrit rien) |
| `assets/products/uploads/` | images | images envoyées depuis l'admin, nom aléatoire de 24 caractères hexadécimaux |
| Sauvegarde | JSON téléchargé | export depuis Maintenance (voir 6.9) ; **pas de fonction d'import** |
| Sessions PHP | fichiers | gérées par PHP ; dossier `C:/wamp/tmp` (`phpForApache.ini`, hors code) |

---

## 6. Authentification, sessions et sécurité

### 6.1 Connexion

- Identifiant = adresse e-mail, vérification par `password_verify()` (`admin/login.php`).
- Succès : `throttle_reset('login')`, `login()` (nouvel ID de session), journal `login_ok`.
- Échec : `throttle_fail('login')`, journal `login_fail`, message avec le délai d'attente.

### 6.2 Session (`app/bootstrap.php`)

- Cookie `INFOLEADER_SID`, `HttpOnly`, `SameSite=Strict`, `Secure` seulement si HTTPS.
- `session.use_strict_mode` et `session.use_only_cookies` activés.
- ID régénéré à la connexion (`login()`), à la déconnexion (`logout()`) et au changement de mot de passe (`admin/password.php`).
- Déconnexion automatique après **30 min d'inactivité** (`SESSION_IDLE = 1800`, vérifiée dans `current_admin()`).
- Déconnexion en POST + CSRF uniquement (`admin/logout.php`).

### 6.3 Contrôle d'accès

`require_login()` est appelé en tête des 6 pages protégées (`index`, `products`, `email`, `password`, `user`, `maintenance`). Les tests le vérifient (`tests/http_test.php`, fin du fichier).

### 6.4 CSRF

Un jeton de 64 caractères hexadécimaux est stocké en session (`csrf_token()`). Il est inséré dans chaque formulaire (`csrf_field()`). Tout POST sans jeton valide, ou avec une session sans jeton, est refusé en **400** (`app/bootstrap.php`). La comparaison utilise `hash_equals()`.

### 6.5 Anti force brute (`throttle_*`, `app/bootstrap.php`)

- Stocké en base, par IP (`REMOTE_ADDR` seul) et par action : `login`, `forgot`, `reset`, `password`, `contact`.
- Délais : 3 s après le 1er échec, puis 6, 12, 24 s (plafond 60 s).
- 5e échec : IP bloquée 15 min pour cette action, journal `blocked`.
- Les échecs de plus d'une heure sont oubliés (`THROTTLE_FORGET_AFTER`).
- Pour `forgot` et `contact`, **chaque** envoi compte comme un échec (anti-spam).
- Le bouton affiche un compte à rebours (`data-wait`, `script.js`) ; c'est du confort, la limite est côté serveur.

### 6.6 Mot de passe oublié

- Code à 8 chiffres tiré avec `random_int()`, stocké haché, valable 5 min, un seul code actif (`admin/forgot.php`).
- Réponse identique que l'adresse existe ou non : pas d'énumération des comptes.
- Code invalidé après 5 essais faux ; usage unique (`admin/reset.php`).
- Les règles du nouveau mot de passe sont vérifiées **avant** le code : une faute de frappe ne consomme pas d'essai.

### 6.7 Règles de mot de passe

`password_errors()` et `PASSWORD_RULES` (`app/bootstrap.php`) : au moins 20 caractères, au plus 72 octets (limite de bcrypt), une minuscule, une majuscule, un chiffre, un caractère spécial. Hachage : `password_hash(PASSWORD_DEFAULT)` (bcrypt, préfixe `$2y$` vérifié par les tests).

### 6.8 Autres protections

| Protection | Où |
|---|---|
| En-têtes `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin` | `app/bootstrap.php`, toutes les pages |
| CSP stricte `default-src 'self'` (pas de script ni de style en ligne) | `app/bootstrap.php`, toutes les pages (site public depuis le 15/10/2026, tâche SEC-3) |
| Échappement de toute sortie avec `e()` (`htmlspecialchars`, `ENT_QUOTES`, UTF-8) | toutes les vues |
| Requêtes préparées, `ATTR_EMULATE_PREPARES = false` | `app/bootstrap.php` |
| Uploads : type réel lu avec `finfo` + `getimagesize`, JPEG/PNG/WebP, 2 Mo max, nom aléatoire | `save_uploaded_image()`, `app/catalog.php` |
| Suppression d'image limitée aux fichiers d'`uploads/` au nom aléatoire | `delete_uploaded_image()`, `app/catalog.php` |
| Pas d'injection d'en-tête e-mail : retours à la ligne retirés des champs | `admin/email.php`, `contact.php` |
| Erreurs masquées au visiteur (`display_errors = 0` à l'exécution) | `app/bootstrap.php` |

### 6.9 Secrets

- `storage/config.php` contient les accès BDD et une clé AES de 32 octets (`app_key`). Il est généré par l'installeur et ignoré par Git.
- Le mot de passe SMTP est chiffré en **AES-256-GCM** (`encrypt_secret()` / `decrypt_secret()`), jamais réaffiché dans le formulaire (`admin/email.php`), jamais journalisé.
- Le log SMTP masque les lignes `AUTH` et le contenu des messages (`mail_debug_writer()`, `app/mailer.php`).
- La sauvegarde JSON retire toute clé dont le nom contient `pass`, `secret`, `token`, `api`, `key`, `hash` ou `smtp_user` (`strip_secrets()`, `admin/maintenance.php`).
- La purge de `storage/tmp` ne supprime rien hors du dossier réel et ignore les liens (`purge_tmp()`).

### 6.10 Points forts

- Sécurité traitée de façon centrale : CSRF, session et en-têtes sont gérés une seule fois dans `app/bootstrap.php`. Une nouvelle page est protégée dès qu'elle l'inclut.
- Aucune requête SQL ne concatène une saisie utilisateur.
- Limitation des tentatives persistante en base : elle résiste au rechargement et à la suppression des cookies.
- Principe du moindre privilège : l'utilisateur MySQL du site ne peut pas modifier la structure de la base.
- Secrets jamais en clair dans la base, les formulaires, les journaux ni la sauvegarde.
- Code commenté en français, fonctions courtes, une seule feuille de style et un seul script.
- Suite de tests de bout en bout qui couvre la sécurité (CSRF, XSS, force brute, upload malveillant, accès non connecté) et n'envoie aucun vrai e-mail.
- Site entièrement hors ligne : aucune ressource externe.

---

## 7. Front-end

### HTML

- Documents HTML5 complets, `lang="fr"`, UTF-8, générés par deux gabarits : `site_header()` / `site_footer()` (`app/site.php`) et `admin_header()` / `admin_footer()` (`app/layout.php`). La navigation n'est pas dupliquée.
- Le lien actif reçoit `class="is-active"` et `aria-current="page"`.
- L'admin envoie `<meta name="robots" content="noindex">`.
- La carte produit est produite par `product_card()` : image ou, à défaut, l'initiale du nom.

### CSS (`style.css`, 1141 lignes)

- Variables dans `:root` : bleu `#0056b3`, bleu foncé `#003f86`, gris foncé `#2d2d2d`, vert `#4caf50`, fond `#f2f5f9`, bordure `#dbe2ea`, etc.
- Points de rupture : 900 px (mise en page empilée) et 640 px (menu hamburger).
- `prefers-reduced-motion` respecté (transitions coupées), contour `:focus-visible` pour la navigation au clavier.
- Les liens vers `style.css` et `script.js` portent `?v=filemtime(...)` pour forcer le rechargement après modification.
- **Thème clair/sombre : absent.** Il n'y a qu'un thème clair ; aucune règle `prefers-color-scheme` dans `style.css`.

### JavaScript (`script.js`, vanilla, aucune dépendance)

| Fonction | Rôle |
|---|---|
| `initMobileMenu()` | ouvre/ferme le menu sous 640 px, met à jour `aria-expanded` |
| `initProductFilter()` | filtre les cartes par nom et description, délai de 150 ms, compteur, message « aucun résultat » |
| `initAutoHide()` | masque les messages flash après 5,5 s |
| `initWaitButton()` | compte à rebours sur le bouton après un échec |
| `initConfirm()` | boîte de confirmation avant suppression ou purge (`data-confirm`) |

Le site reste utilisable sans JavaScript : la validation des formulaires est faite par le navigateur (`required`, `type="email"`) **et** par le serveur.

---

## 8. Intégrations externes

### Envoi d'e-mails (Mailjet via PHPMailer)

- Une seule fonction d'envoi : `send_mail($to, $subject, $body, $replyTo)` (`app/mailer.php`). Elle renvoie `null` si l'envoi est accepté, sinon le message d'erreur (aussi journalisé).
- Deux modes : SMTP (par défaut `in-v3.mailjet.com`, port 587, STARTTLS, délai 15 s) ou PHP `mail()`. Sous WAMP, `mail()` n'a pas de serveur d'envoi (indiqué dans `admin/email.php`).
- Utilisations :

| Appelant | Destinataire | Contenu |
|---|---|---|
| `admin/forgot.php` | adresse du compte admin | code de réinitialisation |
| `admin/email.php` | `to_email` | e-mail de test |
| `contact.php` | `to_email`, « Répondre à » = visiteur | message du formulaire |

- L'envoi est désactivé tant que l'expéditeur, l'identifiant et la clé ne sont pas remplis (`mail_ready()`).
- `ETAT-DU-PROJET.md` précise qu'un « 250 OK queued » de Mailjet ne prouve pas la livraison : l'expéditeur doit être validé dans Mailjet.

### Autres services

Aucun. Pas d'API tierce, pas de CDN, pas de statistiques de visite, pas de paiement. Seuls les tests contactent Mailjet, avec de faux identifiants, pour vérifier le refus d'authentification (`tests/http_test.php`).

---

## 9. Configuration

### `storage/config.php` (généré par `app/install.php`)

```php
return [
    'db' => ['host' => …, 'port' => …, 'name' => 'infoleader', 'user' => 'infoleader', 'pass' => <aléatoire>],
    'app_key' => <32 octets aléatoires en base64>,
];
```

Il est lu une seule fois par `config()`. Il ne doit jamais être publié.

### Réglage `mail` (table `settings`, édité dans Admin → E-mails)

Clés et valeurs par défaut (`mail_config()`, `app/mailer.php`) : `transport` (`smtp`), `from_name` (`InfoLeader`), `from_email`, `to_email`, `smtp_host` (`in-v3.mailjet.com`), `smtp_port` (587), `smtp_secure` (`tls`), `smtp_user`, `smtp_pass` (chiffré), `debug` (faux).

### Constantes

| Constante | Valeur | Fichier |
|---|---|---|
| `ROOT`, `STORAGE` | dossier du projet, `ROOT/storage` | `app/bootstrap.php` |
| `SESSION_IDLE` | 1800 s | `app/bootstrap.php` |
| `THROTTLE_BASE_DELAY`, `_MAX_DELAY`, `_MAX_FAILURES`, `_BLOCK_TIME`, `_FORGET_AFTER` | 3 s, 60 s, 5, 900 s, 3600 s | `app/bootstrap.php` |
| `PASSWORD_RULES` | 4 règles affichées | `app/bootstrap.php` |
| `UPLOAD_DIR`, `UPLOAD_MAX_BYTES`, `UPLOAD_TYPES` | `assets/products/uploads`, 2 Mo, JPEG/PNG/WebP | `app/catalog.php` |
| `MAIL_DEBUG_LOG` | `storage/logs/smtp-debug.log` | `app/mailer.php` |
| `RESET_CODE_LIFETIME` | 300 s | `admin/forgot.php` |
| `RESET_MAX_ATTEMPTS` | 5 | `admin/reset.php` |
| `TMP_DIR`, `LOG_TYPES`, `PROTECTED_TMP_FILES` | `storage/tmp`, 5 types, `.htaccess`/`.gitkeep` | `admin/maintenance.php` |
| `SITE_LINKS` | Accueil, Nos Produits, Contact | `app/site.php` |
| `BASE`, `TEST_PASSWORD`, `NEW_PASSWORD` | `http://127.0.0.1/infoleader/`, mots de passe de test | `tests/http_test.php` |

### Réglages PHP

- Dans le code : fuseau `Europe/Paris`, `display_errors = 0`, options de session (`app/bootstrap.php`).
- Dans WAMP (`phpForApache.ini`, hors code) : `post_max_size = 8M`, `upload_max_filesize = 2M`, `display_errors = On`, journal `C:/wamp/logs/php_error.log`. Un POST de plus de 8 Mo arrive vide et échoue donc au contrôle CSRF.

### Installation (`app/install.php`, ligne de commande)

```bash
php composer.phar install
php app/install.php --email=admin@exemple.fr [--root-user=root] [--root-pass=] [--host=localhost] [--port=3306]
```

L'installeur est relançable : il réutilise `storage/config.php`, applique le schéma, importe `products.json` si la table est vide et crée l'admin s'il n'en existe aucun. Le mot de passe initial est affiché une seule fois.

---

## 10. Limites connues et risques

Classement : 🔴 à corriger en priorité · 🟠 important · 🟡 mineur.

### Sécurité

1. 🔴 **Clé secrète Mailjet exposée.** `ETAT-DU-PROJET.md` indique qu'elle « a été partagée dans une conversation » et qu'elle est à régénérer. Tant qu'elle est valide, un tiers peut envoyer des e-mails au nom du compte.
2. ✅ ~~Pas de CSP sur le site public.~~ Corrigé le 15/10/2026 (tâche SEC-3) : l'en-tête `Content-Security-Policy` est désormais envoyé par `app/bootstrap.php` pour toutes les pages, publiques et admin.
3. 🟠 **Compte MySQL `root` sans mot de passe** par défaut sous WAMP. L'installeur le suppose (`--root-pass` vide). Tout programme local peut alors lire ou effacer la base.
4. 🟡 **Limitation de débit sans verrou.** Deux requêtes strictement simultanées peuvent passer pendant un délai (commentaire `ponytail:` dans `throttle_fail()`, `app/bootstrap.php`).
5. ✅ ~~Énumération possible par le temps de réponse.~~ Corrigé le 15/10/2026 (tâche SEC-4) : `admin/login.php` vérifie toujours un hachage (factice si l'adresse est inconnue).
6. 🟡 **Les autres sessions restent ouvertes** après un changement ou une réinitialisation de mot de passe (`admin/password.php`, `admin/reset.php`). Seule la session courante change d'ID.
7. 🟡 **`display_errors = On` dans `phpForApache.ini`.** Le code le désactive à l'exécution, mais une erreur de syntaxe PHP s'afficherait avant, avec le chemin des fichiers.

### Conformité / RGPD (Règlement général sur la protection des données)

8. ✅ ~~Aucune information sur l'usage des données sous le formulaire de contact.~~ Corrigé le 15/10/2026 (tâche RGPD-1) : une mention sous le bouton « Envoyer » indique l'usage, le destinataire, la conservation et la façon de demander la suppression (`contact.php`). La durée de conservation reste « jusqu'à suppression par l'administrateur » (voir L10).
9. ✅ ~~Pas de page « Mentions légales / Confidentialité ».~~ Corrigé le 15/10/2026 (tâche RGPD-2) : `mentions-legales.php`, reliée dans `site_footer()` (`app/site.php`) et depuis la mention du formulaire de contact.
10. ✅ ~~Aucune durée de conservation.~~ Corrigé le 15/10/2026 (tâche RGPD-3) : à chaque ouverture du tableau de bord, `admin/index.php` supprime les journaux de plus de 12 mois (et les compteurs anti force brute périmés). La mention du formulaire de contact et `mentions-legales.php` annoncent « 12 mois au maximum ».
11. ✅ ~~La sauvegarde JSON contient les messages de contact.~~ Corrigé le 15/10/2026 (tâche RGPD-4) : l'onglet Sauvegarde propose « Exclure les messages de contact » (coché par défaut).

### Stockage et exploitation

12. ✅ ~~Sauvegarde non restaurable.~~ Corrigé le 15/10/2026 (tâche EXPL-1) : `README.md` (section « Sauvegarde et restauration de la base ») décrit une sauvegarde `mysqldump` restaurable et sa vérification dans une base vide ; `*.sql` est ignoré par Git (sauf `app/schema.sql`). L'export JSON reste un export de consultation.
13. ✅ ~~Log SMTP sans limite de taille.~~ Corrigé le 15/10/2026 (tâche EXPL-4) : l'onglet « Fichiers temporaires » de Maintenance permet de vider `storage/logs/smtp-debug.log` (avec confirmation).
14. ✅ ~~`products.json` reste accessible.~~ Corrigé le 15/10/2026 (tâche DATA-2) : le `.htaccess` racine refuse `products.json` (403).
15. 🟡 **Mode PHP `mail()` non fonctionnel sous WAMP** : il est enregistrable mais n'envoie rien.
16. ✅ ~~Message de contact tronqué dans les journaux.~~ Corrigé le 15/10/2026 (tâche DATA-1) : `log_event()` (`app/bootstrap.php`) garde désormais 6000 caractères, assez pour un message complet (5000) avec le nom et l'e-mail.

### Qualité du code et tests

17. ✅ ~~Caractère invisible dans `contact.php`.~~ Corrigé le 15/10/2026 (tâche CODE-1) : `contact.php` n'a plus de caractère de contrôle brut (`\r`, `\n` échappés) et suit le style du reste du projet (`is_post()`, `[]`, `<?=`, guillemets simples).
18. ✅ ~~Tests dépendants de WAMP, message peu clair.~~ Corrigé le 15/10/2026 (tâche CODE-2) : `tests/http_test.php` vérifie d'abord MySQL et Apache ; s'ils ne répondent pas, il affiche « Apache ou MySQL ne répond pas : démarrez WAMP (icône verte) » et s'arrête avec le code 1.
19. 🟡 **Les tests écrivent dans la vraie base** `infoleader` : ils restaurent le compte, la configuration e-mail et les messages de contact, mais laissent des entrées de test dans `logs` (échecs, blocages, événements).
20. 🟡 **Parties non testées automatiquement** : boîtes de confirmation JavaScript, filtre de recherche, menu mobile (`ETAT-DU-PROJET.md`, « Reste à faire »).
21. 🟡 **Documentation existante en partie obsolète.** `README.md` dit encore que « le formulaire de contact n'envoie toujours pas d'e-mail » et annonce 84 vérifications ; `ETAT-DU-PROJET.md` en annonce 86. Le commentaire d'en-tête d'`admin/index.php` parle de « 4 modules » alors qu'il y en a 5.

### Front-end

22. 🟡 **Pas de thème sombre** (aucune règle `prefers-color-scheme`).
23. ✅ ~~Défilement fluide JavaScript.~~ Corrigé le 15/10/2026 (tâche FRONT-2) : `initSmoothScroll()` est supprimée de `script.js` ; le défilement des ancres passe par `scroll-behavior: smooth` en CSS, désactivé par `prefers-reduced-motion`.
24. ✅ ~~Pas de page 404 personnalisée.~~ Corrigé le 15/10/2026 (tâche FRONT-3) : lien « Aller au contenu » ajouté (`app/site.php`, `app/layout.php`, `.skip-link`). La page 404 reste à faire (FRONT-4).
25. 🟡 **Images du TP1 provisoires** (dégradé + texte, `ETAT-DU-PROJET.md`).

### Limites assumées (choix du TP, pas des défauts)

- Un seul compte administrateur, pas de comptes visiteurs.
- Pas de stock, de panier ni de commande : le catalogue est une vitrine.
- Pas de HTTPS en local : le cookie de session n'a donc pas l'attribut `Secure` (`app/bootstrap.php`).
