# [TP] InfoLeader — site vitrine PHP + panel d'administration

> **Projet scolaire (travaux pratiques TP1 + TP2).**
> Exercice de formation au développement web. Ce n'est ni un projet professionnel ni un projet personnel.
> « InfoLeader » est une entreprise fictive inventée pour le TP. Le code tourne en local sous WAMP et n'est pas prévu pour la production.

| TP | Objectif |
|---|---|
| **TP1** | Site vitrine en 3 pages : accueil, produits (recherche en temps réel), contact |
| **TP2** | Panel d'administration sécurisé : connexion anti-force brute, mot de passe oublié par e-mail, envoi réel via Mailjet, modules Mot de passe, Utilisateur, Articles et Maintenance |

L'état détaillé du projet (stack, fonctionnalités, avancement) est dans [`ETAT-DU-PROJET.md`](ETAT-DU-PROJET.md).

## Prérequis

- WAMP : Apache 2.4 (`AllowOverride all`), PHP 8.1+ (extensions `pdo_mysql`, `openssl`, `mbstring`), MySQL 8.
- Composer (pour PHPMailer). Sans Composer installé : `php composer.phar` (https://getcomposer.org/download/).

## Installation

```bash
cd C:\wamp\www\infoleader
composer install                                   # PHPMailer (dossier vendor/)
php app/install.php --email=votre@adresse.fr       # BDD, utilisateur MySQL dédié, compte admin
```

`app/install.php` (ligne de commande uniquement) :
- crée la base `infoleader` et l'utilisateur MySQL `infoleader@localhost` (droits SELECT/INSERT/UPDATE/DELETE seulement) ;
- applique `app/schema.sql` (migration SQL, relançable) ;
- génère `storage/config.php` (accès BDD + clé AES de chiffrement — ne jamais publier) ;
- crée le premier administrateur et **affiche son mot de passe une seule fois**.

Options : `--root-user=root --root-pass= --host=localhost --port=3306`.

Ouvrir ensuite `http://localhost/infoleader/` (site) et `http://localhost/infoleader/admin/` (administration).

## Configurer Mailjet

1. Compte Mailjet → **Account settings → Sender addresses & domains** : valider l'adresse expéditrice.
2. **Account settings → API Key Management** : noter l'*API Key* et la *Secret Key*.
3. Admin → **E-mails** : méthode Mailjet SMTP, serveur `in-v3.mailjet.com`, port `587`, STARTTLS,
   identifiant = API Key, mot de passe = Secret Key, expéditeur = adresse validée, destinataire = votre boîte.
4. **Enregistrer et envoyer un test**, puis vérifier la réception (et les spams).
5. Tester « Mot de passe oublié » : le code doit arriver sur l'adresse du compte admin (module Utilisateur).

La Secret Key est chiffrée (AES-256-GCM) en base, jamais réaffichée, jamais journalisée.
Le log de débogage (`storage/logs/smtp-debug.log`) masque l'authentification et le contenu des messages.

## Structure

```
infoleader/
├── index.php, products.php, contact.php   ← site public (TP1)
├── style.css, script.js, products.json, assets/
├── admin/            ← login, forgot, reset, index (tableau de bord), products (articles), email, password, user, maintenance, logout
├── app/              ← bootstrap.php (session, CSRF, BDD, anti force brute...), layout.php (admin), site.php (site public),
│                        catalog.php (articles), mailer.php,
│                        install.php, schema.sql      (accès web interdit)
├── storage/          ← config.php, tmp/, logs/       (accès web interdit)
├── tests/http_test.php                              (accès web interdit)
└── vendor/           ← Composer                      (accès web interdit)
```

## Sécurité

- Requêtes préparées PDO (émulation désactivée), échappement HTML systématique, CSP stricte dans l'admin.
- Jeton CSRF vérifié sur **tout** POST ; cookie de session HttpOnly + SameSite=Strict ; ID régénéré à la connexion ; expiration après 30 min d'inactivité.
- Mots de passe : `password_hash()` / `password_verify()`, 20 caractères minimum, minuscule, majuscule, chiffre, caractère spécial.
- Anti force brute en base, par IP et par action (connexion, oubli, code, mot de passe actuel) :
  3 s après le 1er échec, puis 6, 12, 24 s ; au 5e échec, IP bloquée 15 min. Résiste aux rechargements.
- Code de réinitialisation : 8 chiffres, haché, valable 5 min, usage unique, invalidé après 5 essais. Réponse identique que le compte existe ou non.

## Tests

```bash
php tests/http_test.php
```

Tests de bout en bout contre Apache (84 vérifications). L'envoi d'e-mails est désactivé pendant les tests. Le compte admin et la configuration e-mail sont sauvegardés puis restaurés.
L'étape Mailjet se connecte réellement à `in-v3.mailjet.com` avec de faux identifiants (refus d'authentification attendu).

## Notes

- Le formulaire de contact n'envoie toujours pas d'e-mail : le message est enregistré dans les journaux (Maintenance → Journaux).
- Les articles sont en base (table `products`) et se gèrent dans Admin → **Articles** (ajout, modification, suppression, image JPEG/PNG/WebP ≤ 2 Mo).
  `products.json` ne sert plus qu'à l'import initial par `app/install.php`. Images envoyées : `assets/products/uploads/` (aucun script exécutable).
