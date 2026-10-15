# InfoLeader — Stack, site et état d'avancement

*Mis à jour le 15/10/2026 (TP 2bis : voir `TODO_LIST.md` pour les améliorations faites depuis).*

## 1. Le site en bref

InfoLeader est le site vitrine d'une entreprise de matériel informatique. Le TP1 a produit le site public. Le TP2 a ajouté un panel d'administration sécurisé, l'envoi réel d'e-mails et la gestion du catalogue.

| Partie | Adresse locale |
|---|---|
| Site public | `http://localhost/infoleader/` |
| Administration | `http://localhost/infoleader/admin/` |

---

## 2. Stack technique

| Couche | Technologie | Détail |
|---|---|---|
| Serveur local | **WAMP** (Windows) | Apache 2.4.65, `AllowOverride all` (les `.htaccess` protègent les dossiers sensibles) |
| Langage serveur | **PHP 8.3.28** | PHP natif, sans framework. Extensions : `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl` (tests) |
| Base de données | **MySQL 8.4.7** | Base `infoleader`, utilisateur dédié `infoleader@localhost` limité à SELECT/INSERT/UPDATE/DELETE |
| Accès BDD | **PDO** | Requêtes préparées uniquement (émulation désactivée) |
| E-mails | **PHPMailer 6.12** + **Mailjet SMTP** | `in-v3.mailjet.com:587`, STARTTLS |
| Dépendances | **Composer** | Une seule : `phpmailer/phpmailer` |
| Front | HTML5, **CSS** natif, **JavaScript vanilla** | Aucun framework (ni Bootstrap, ni jQuery), aucune ressource externe : le site fonctionne hors ligne |
| Police | Pile système sans-serif | `Segoe UI Variable` sous Windows 11, `system-ui` ailleurs |
| Tests | Script PHP + cURL | `php tests/http_test.php` : 98 vérifications de bout en bout contre Apache |

### Arborescence

```
infoleader/
├── index.php, products.php, contact.php   ← site public
├── style.css, script.js                    ← un seul CSS et un seul JS pour tout le site
├── admin/        ← connexion, mot de passe oublié, tableau de bord, 6 modules
├── app/          ← code partagé (bootstrap, gabarits, catalogue, mailer, installeur, schéma SQL)
├── storage/      ← configuration secrète, fichiers temporaires, logs SMTP
├── assets/       ← bannière, images produits, images envoyées depuis l'admin
├── tests/        ← tests automatisés
└── vendor/       ← PHPMailer (Composer)
```

`app/`, `storage/`, `vendor/`, `tests/`, `composer.*` et les fichiers `.md` renvoient **403** depuis le navigateur.

### Base de données (6 tables)

| Table | Rôle |
|---|---|
| `admins` | Compte administrateur (e-mail + mot de passe haché) |
| `password_resets` | Codes « mot de passe oublié » (hachés, 5 min, usage unique) |
| `throttle` | Limitation des tentatives par IP et par action |
| `settings` | Configuration e-mail (clé Mailjet chiffrée) |
| `logs` | Journaux : connexions, échecs, blocages, événements, messages de contact |
| `products` | Articles du catalogue |

---

## 3. Fonctionnalités

### Site public

- **Accueil** : grande bannière avec le titre à gauche, encart « Nouveautés » (les 3 derniers articles) qui chevauche le bas de la bannière, section « Qui sommes-nous ? ».
- **Nos Produits** : grille des articles lus en base, recherche en temps réel avec compteur et message quand rien ne correspond.
- **Contact** : formulaire validé par le navigateur et par le serveur. Chaque message est enregistré dans les journaux **et** envoyé par e-mail à l'adresse configurée. « Répondre » écrit directement au visiteur. Limite anti-spam par IP.
- Menu et pied de page communs aux trois pages, adaptés au mobile (menu hamburger sous 640 px).

### Administration

| Module | Ce qu'il permet |
|---|---|
| **Connexion** | Accès protégé. Délais progressifs après échec (3, 6, 12, 24 s), IP bloquée 15 min au 5e échec |
| **Mot de passe oublié** | Code à 8 chiffres envoyé par e-mail, valable 5 minutes |
| **Tableau de bord** | Compte connecté, heure de session, connexion précédente, accès aux modules |
| **Articles** | Ajouter, modifier, supprimer un article, avec image (JPEG, PNG, WebP, 2 Mo max) |
| **E-mails** | Configurer Mailjet SMTP ou PHP mail, enregistrer, envoyer un vrai e-mail de test, logs de débogage |
| **Mot de passe** | Changer le mot de passe (20 caractères min., majuscule, minuscule, chiffre, caractère spécial) |
| **Utilisateur** | Changer l'adresse e-mail du compte (mot de passe actuel exigé) |
| **Maintenance** | État du système, journaux filtrables et purgeables, sauvegarde JSON sans secrets, purge de `storage/tmp` |

### Sécurité

- Jeton CSRF sur tous les formulaires ; un POST sans jeton est refusé (400).
- Requêtes SQL préparées, échappement HTML systématique, politique CSP stricte dans l'admin.
- Session sécurisée : cookie HttpOnly, SameSite=Strict, ID régénéré à la connexion, déconnexion après 30 min d'inactivité.
- Mots de passe hachés avec `password_hash()` et vérifiés avec `password_verify()`.
- Clé Mailjet chiffrée en base (AES-256-GCM), jamais réaffichée ni journalisée. Identifiants masqués dans le log SMTP.
- Images envoyées : type réel vérifié, nom aléatoire, aucun script exécutable dans le dossier.

---

## 4. État d'avancement

### Terminé et vérifié

| Élément | Comment c'est vérifié |
|---|---|
| Connexion + anti-brute-force | Tests automatisés (délais, blocage qui résiste au rechargement, journalisation) |
| Mot de passe oublié | Tests automatisés **et** utilisation réelle : code reçu par e-mail puis mot de passe changé (13/10, 09:35) |
| Configuration Mailjet | Authentification acceptée par Mailjet, e-mail de test reçu dans la boîte Gmail |
| Modules Mot de passe et Utilisateur | Tests automatisés (règles, confirmation, mot de passe actuel, XSS) |
| Maintenance (4 outils) | Tests automatisés (filtres, purges avec confirmation, sauvegarde sans secrets, sécurité de la purge) |
| Module Articles | Tests automatisés (ajout, modification, image, faux fichier refusé, suppression, affichage public) |
| Nouvelle esthétique | Captures d'écran du site public sur ordinateur et sur mobile (390 px) |
| Protection des dossiers | Requêtes HTTP : 403 sur `app/`, `storage/`, `vendor/`, `tests/`, `composer.json`, `.md` |

**Suite de tests : 98 vérifications** (dernière exécution complète : 0 échec, 2 vérifications Mailjet ignorées car Mailjet bloquait temporairement). Aucun e-mail réel n'est envoyé pendant les tests.

### Bugs corrigés en cours de route

- Un POST sans jeton CSRF passait quand la session n'avait pas encore de jeton.
- Le formulaire de contact du TP1 appelait une fonction JS `validateForm()` qui n'existait pas.
- PHP était en heure UTC et MySQL en heure de Paris : les heures affichées ne concordaient pas.
- Les premiers tests envoyaient de vrais e-mails de code à la boîte de l'administrateur.

### Reste à faire ou à vérifier

| Point | Statut |
|---|---|
| Réception des messages du formulaire de contact | Mailjet accepte l'envoi, mais la réception du 1er message de test n'est **pas encore confirmée**. Vérifier dans Mailjet → Statistics → Messages, ou envoyer un message avec une vraie adresse |
| Écran admin « Articles » | Fonctionnel et testé, mais pas encore contrôlé visuellement |
| Images des produits | Les 6 images du TP1 sont des visuels provisoires (dégradé + texte). À remplacer via Admin → Articles |
| Clé secrète Mailjet | Elle a été partagée dans une conversation : à **régénérer** dans Mailjet puis à saisir dans Admin → E-mails |
| Boîtes de confirmation JavaScript (purges, suppression) | Non testées automatiquement ; seule la vérification côté serveur l'est |
| Purge de `storage/tmp` face aux liens symboliques | Prévue dans le code, non testée sous Windows |
| Mode PHP `mail()` | Enregistrable, mais non fonctionnel sous WAMP sans serveur mail |
| Mise en production | Nécessiterait HTTPS (signalé en « Attention » dans Maintenance → Système) |

### Limites connues

- Deux requêtes strictement simultanées peuvent contourner un délai anti-brute-force (pas de verrou en base).
- Un seul compte administrateur, pas de comptes visiteurs.
- Pas de gestion de stock ni de commande : le catalogue est une vitrine.

---

## 5. Démarrer le projet

```bash
cd C:\wamp\www\infoleader
php composer.phar install                    # PHPMailer
php app/install.php --email=votre@adresse.fr # base, tables, articles, compte admin
php tests/http_test.php                      # vérification complète
```

Puis démarrer WAMP et ouvrir `http://localhost/infoleader/`. Détails d'installation et de configuration Mailjet : voir `README.md`.
