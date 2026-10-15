# Journal des consignes données à l'agent IA (TP 2bis)

*Trace de la démarche, pour la transparence sur l'usage de l'IA.*

## Outil utilisé

| Élément | Valeur |
|---|---|
| Agent | **Claude Code** (Anthropic), application de bureau, lancé à la racine du site `C:\wamp\www\infoleader` |
| Remarque | Le TP prévoit l'agent OpenCode. Les consignes ci-dessous sont celles du TP, appliquées avec Claude Code. |
| Date | 15/10/2026 |

---

## 1. Consigne de départ

> il faut que le projet corresponde a ce nouveau tp

Suivie du sujet complet du TP 2bis (parties A à E). L'agent a donc appliqué, dans l'ordre, les trois consignes du TP reproduites ci-dessous.

---

## 2. Partie A — Analyse et `DOCUMENTATION.md` (consigne A.2 du TP)

```
Tu es développeur web senior. Analyse l'intégralité du site web présent dans le dossier courant et rédige un fichier DOCUMENTATION.md à la racine du projet.

Règles impératives :
  - Base-toi UNIQUEMENT sur le code réellement présent. N'invente rien : si une information manque, indique-le explicitement.
  - Écris en français, au format Markdown, avec une table des matières en haut du document.
  - Utilise un ton professionnel et des phrases courtes.

Plan attendu du document :
  1. Présentation générale du site (à quoi il sert, fonctionnalités principales).
  2. Stack technique (langages, versions, serveur, stockage des données, bibliothèques).
  3. Arborescence du projet (dossiers et fichiers importants, avec leur rôle).
  4. Points d'entrée et routage (comment une URL est traitée par le site).
  5. Stockage des données : indique où et comment les données sont stockées (à ce stade, le plus souvent des fichiers JSON). Si le site utilise une base de données, décris-la (tables, colonnes, relations, requêtes).
  6. Authentification, sessions et sécurité.
  7. Front-end (HTML, CSS, JavaScript, thème clair/sombre...).
  8. Intégrations externes (API, envoi d'e-mails, services tiers...).
  9. Configuration (fichiers de configuration, variables, constantes).
  10. Limites connues et risques : bugs potentiels, failles de sécurité, absence de tests, points fragiles à corriger en priorité.

Écris directement le fichier DOCUMENTATION.md.
```

Consignes d'affinage du TP également appliquées dès la première version :

```
Vérifie que tu n'as rien inventé : pour chaque affirmation, cite le fichier qui la justifie. Corrige DOCUMENTATION.md en conséquence.

Ajoute dans DOCUMENTATION.md une sous-section « Points forts » qui liste ce qui est déjà bien fait dans le site.
```

**Vérifications faites sur le code réel**

- Lecture de tous les fichiers PHP, SQL, CSS et JS du projet (hors `vendor/`).
- Versions lues dans les dossiers de WAMP (`C:\wamp\bin\…`) et dans `composer.lock`, signalées « hors code » dans le document.
- Réglages Apache/PHP lus dans `phpForApache.ini` (limites d'envoi, `display_errors`, dossier des sessions).
- Limites confirmées par des commandes : `file contact.php` (retour chariot brut), lancement de `php tests/http_test.php` avec WAMP arrêté (message « Erreur interne »), comparaison des limites 5000 / 2000 caractères (`contact.php` / `log_event()`).
- Le site utilise **MySQL**, pas des fichiers JSON : la section 5 décrit donc la base (6 tables, colonnes, clé étrangère, requêtes) et le rôle réel des fichiers JSON.

---

## 3. Partie C — `TODO_LIST.md` (consigne C du TP)

```
Lis le fichier DOCUMENTATION.md présent à la racine du projet et génère un fichier TODO_LIST.md à la racine.

Contexte : je suis étudiant en BTS SIO 1re année. Les améliorations doivent être à ma portée et réalisables dans le cadre de mes TP.

Environnement imposé (à respecter absolument) :
  - Le site tourne uniquement en local sur WAMP (Apache + PHP + MySQL sous Windows).
  - Ne propose AUCUN serveur distant, VPS, Docker, Nginx, PHP-FPM, déploiement automatisé ni nom de domaine réel.
  - N'impose AUCUNE nouvelle dépendance (Composer, npm, framework) sans le signaler explicitement comme "sous accord du professeur".
  - Aucun paiement réel : toute fonctionnalité de paiement doit être une SIMULATION (aucune passerelle bancaire, aucune donnée de carte).
  - Stockage des données : elles sont actuellement dans des fichiers JSON. L'absence de base de données n'est PAS un défaut ; ne la signale pas comme une erreur.
  - Tu peux proposer, comme évolution optionnelle, un stockage en base SQLite (intégrée à PHP sous WAMP, aucune dépendance à installer).
  - Les actions doivent être adaptées à mon niveau : privilégie le simple et le concret.

Contenu attendu :
  - Organise les actions par thème (sécurité, stockage des données, qualité du code, front-end, conformité/RGPD, documentation, exploitation locale...).
  - Pour chaque action, indique : un titre clair, une priorité (P0 = critique, P1 = important, P2 = optionnel), un niveau de complexité (simple / moyen / complexe) et un critère d'acceptation vérifiable.
  - Ajoute une section "Définition de Terminé" : ce qui doit être vrai pour qu'une tâche soit considérée comme terminée.
  - Ajoute un séquencement par jalons (étapes), du plus urgent au moins urgent.
  - Vouvoie-moi et explique brièvement les termes techniques.

Écris directement le fichier TODO_LIST.md.
```

Consigne d'affinage du TP appliquée dès la première version :

```
Ajoute dans chaque tableau une colonne "Fichiers concernés" qui indique les fichiers de mon site à modifier.

Vérifie que le plan ne demande aucune installation logicielle supplémentaire. Si c'est le cas, reformule ou marque l'action "sous accord du professeur".
```

**Adaptations et contrôles**

- La consigne suppose un stockage JSON. Le site est déjà en MySQL : le plan le dit et n'impose pas de migration SQLite.
- Aucune action ne demande d'installer un logiciel : `mysqldump`, phpMyAdmin et le menu de WAMP sont fournis avec WAMP.
- Aucune action ne concerne un serveur distant ou un paiement.
- Chaque action renvoie à la limite (L…) de `DOCUMENTATION.md` qui la justifie.

---

## 4. Partie E — Évolution du site (consigne E.1 du TP)

```
Lis le fichier TODO_LIST.md et fais évoluer le site pour mettre en œuvre les tâches, en commençant par les priorités P0.

Règles :
  - Traite les tâches UNE PAR UNE, de la plus prioritaire à la moins prioritaire.
  - Avant chaque tâche : explique ce que tu vas faire et quels fichiers tu vas modifier.
  - Après chaque tâche : indique précisément comment je peux la tester dans mon navigateur (WAMP local).
  - Le site doit rester fonctionnel à tout moment.
  - Contraintes : environnement WAMP local ; aucun paiement réel (simulation uniquement) ; aucune nouvelle dépendance sans mon accord ; modifications adaptées à mon niveau.
  - Ne passe à la tâche suivante qu'après ma validation.

Commence par la première tâche P0.
```

**Tâches traitées**

| Tâche | Ce qui a été fait | Vérification |
|---|---|---|
| SEC-1 | Contrôle des fichiers suivis par Git et de tout l'historique | `git ls-files storage` → 3 fichiers de protection seulement ; `git log --all -- storage/config.php` → vide ; dépôt `tp-infoleader-php` public, sans secret |
| RGPD-1 | Mention d'information sous le formulaire de contact (`contact.php`, classe `.form-notice` dans `style.css`) + vérification automatique dans `tests/http_test.php` | `php tests/http_test.php` → 87 réussis, 0 échec ; texte vérifié dans le navigateur sous le bouton « Envoyer le message » |
| SEC-3 | En-tête CSP déplacé de `app/layout.php` vers `app/bootstrap.php` : une seule ligne protège toutes les pages ; 4 vérifications ajoutées dans `tests/http_test.php` | `php tests/http_test.php` → 91 réussis, 0 échec ; dans le navigateur, recherche produits et menu mobile fonctionnent, console sans erreur |
| RGPD-2 | Page `mentions-legales.php` (entreprise fictive, hébergement local, données collectées, droits, cookie de session), lien dans le pied de page et depuis la mention du contact, styles `.legal-wrap` ; 2 vérifications ajoutées | `php tests/http_test.php` → 93 réussis, 0 échec ; page contrôlée dans le navigateur (ordinateur et mobile 375 px, sans débordement), console sans erreur |
| RGPD-3 | Purge automatique dans `admin/index.php` (journaux > 12 mois, compteurs `throttle` périmés, suppression journalisée) ; textes RGPD mis à jour ; retour chariot brut de `contact.php` remplacé par `\r` (il avait été converti par erreur lors de l'édition) ; 1 vérification ajoutée | `php tests/http_test.php` → 94 réussis, 0 échec (entrée de 13 mois supprimée, 11 mois conservée) |
| EXPL-1 | Procédure `mysqldump` / `mysql -e source` dans `README.md`, testée pour de vrai ; `*.sql` ajouté à `.gitignore` | Export de 67 Ko, restauré dans `infoleader_restore` : 6 tables, 6 articles, 1 admin ; base temporaire et fichier supprimés ensuite |
| DATA-1 | Limite de `log_event()` portée de 2000 à 6000 caractères ; 1 vérification ajoutée | `php tests/http_test.php` → 95 réussis, 0 échec (message de 4000 caractères retrouvé en entier) |
| CODE-1 | Partie PHP de `contact.php` réécrite dans le style du projet ; sauts de ligne bruts remplacés par `\n` | `file contact.php` sans « with CR » ; sujet et corps de l'e-mail comparés à l'ancienne version : identiques ; `php tests/http_test.php` → 95 réussis, 0 échec |
| CODE-2 | Contrôle de MySQL (`SELECT 1`) et d'Apache (cURL) en tête de `tests/http_test.php`, message clair et code de sortie 1 | Panne simulée (Apache sur un port fermé) → message clair, code 1 ; WAMP démarré → 95 réussis, 0 échec, code 0 |
| DATA-2 | `products.json` ajouté au `FilesMatch` du `.htaccess` ; tests 403 sur `products.json`, `composer.json`, `README.md`. Au passage : `send_mail()` journalise aussi l'échec « configuration incomplète » ; les tests désactivent l'envoi après la section Mailjet (fin d'un test instable) et ignorent, en le signalant, les 2 vérifications réseau si Mailjet ne répond pas | `php tests/http_test.php` → 96 réussis, 0 échec, 2 ignorées (Mailjet bloquait temporairement) |
| FRONT-2 | Fonction `initSmoothScroll()` (inutilisée, ignorait « réduire les animations ») supprimée de `script.js` | Recherche produits et menu mobile vérifiés ; console sans erreur |
| CODE-3 | Commentaire « 4 modules » d'`admin/index.php` corrigé ; celui de `script.js` a disparu avec FRONT-2 | Relecture ; aucun changement de comportement |
| RGPD-4 | Case « Exclure les messages de contact » dans Maintenance → Sauvegarde (cochée par défaut) | Syntaxe PHP vérifiée (tests sautés à la demande de l'étudiant) |
| EXPL-4 | Bouton « Vider le log SMTP » avec case de confirmation dans Maintenance (onglet renommé « Fichiers temporaires ») | Syntaxe PHP vérifiée (tests sautés à la demande de l'étudiant) |

*(Compléter ce tableau à chaque nouvelle tâche validée.)*

---

## 5. Mes consignes suivantes

*(Ajouter ici, dans l'ordre, chaque nouvelle consigne donnée à l'agent et sa réponse importante.)*

| Consigne | Réponse de l'agent |
|---|---|
| « WAMP est démarré. Lance les tests, puis commit et push des fichiers sur tp-infoleader-php si tout est vert. » | L'agent a constaté que les services WAMP étaient encore arrêtés et n'a rien publié tant que les tests n'étaient pas lancés. |
| Demande d'ajout d'un second serveur SMTP pour les TP (identifiants **volontairement non reproduits ici** : ce sont des secrets) | Aucun code à modifier : le module Admin → E-mails accepte n'importe quel serveur SMTP (un seul actif à la fois). L'agent a refusé de saisir lui-même le mot de passe et a conseillé de le faire changer, puisqu'il a été écrit dans la conversation. |
| « WAMP est lancé, icône verte. Lance les tests, puis commit et push sur tp-infoleader-php si tout est vert. » | Tests : 87 réussis, 0 échec. Rendu vérifié dans le navigateur. Commit puis push sur le dépôt public. |
| « Passe à la tâche SEC-3 : explique d'abord ce que tu vas modifier, puis fais-la et dis-moi comment la tester. » | Explication donnée avant la modification (déplacer la CSP dans `app/bootstrap.php`), contrôle préalable de l'absence de code en ligne, puis modification, tests (91/91) et procédure de test dans le navigateur. |
| « Passe à la tâche RGPD-2 : explique d'abord ce que tu vas modifier, puis fais-la et dis-moi comment la tester. » | Explication des fichiers touchés, création de la page, tests (93/93), contrôle dans le navigateur. |
| « Demande plus d'autorisation, fais juste. » | L'agent enchaîne désormais les tâches de `TODO_LIST.md` sans demander de validation : une tâche = un commit poussé sur GitHub. Les tâches qui demandent une action manuelle (clé Mailjet, mot de passe root MySQL, réglages WAMP, photos) restent pour l'étudiant. |
