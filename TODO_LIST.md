# InfoLeader — Plan d'améliorations (TODO_LIST)

*Établi le 15/10/2026 à partir de `DOCUMENTATION.md` (section 10 « Limites connues et risques »).*
*Les numéros « L… » renvoient aux limites de cette section.*

## Cadre de travail

Ce plan est pensé pour vous, étudiant en BTS SIO 1re année. Toutes les actions respectent les règles suivantes :

- **Environnement** : le site reste en local sous WAMP (Apache + PHP + MySQL sous Windows), dans `C:\wamp\www\infoleader\`.
- **Dépendances** : aucune nouvelle bibliothèque, aucun framework, aucun outil à installer. Les seuls outils cités (`mysqldump`, phpMyAdmin, le menu de WAMP) sont **déjà fournis avec WAMP**.
- **Paiement** : le site n'a aucune fonction de paiement et ce plan n'en ajoute pas. Si un panier était ajouté un jour, il resterait une simulation, sans aucune donnée bancaire.
- **Stockage** : les données sont déjà dans une base **MySQL** (6 tables). La migration vers SQLite suggérée par le TP n'est donc pas utile ici. Elle reste possible (l'extension `pdo_sqlite` est active dans WAMP) mais n'apporterait rien à ce site.
- **Niveau** : chaque action est courte et testable dans le navigateur. Les actions « complexe » sont toutes en P2 (optionnelles).

**Légende**

- Priorité : **P0** = critique (à faire tout de suite) · **P1** = important · **P2** = optionnel.
- Complexité : **simple** (moins d'une heure, un ou deux fichiers) · **moyen** (une séance de TP) · **complexe** (plusieurs séances, plusieurs fichiers).
- État : ⬜ à faire · ⏳ codé, en attente de votre validation · ✅ terminé (avec la date).

---

## 1. Sécurité

| # | Action | Prio | Complexité | Fichiers concernés | Critère d'acceptation | État |
|---|---|---|---|---|---|---|
| SEC-1 | Vérifier qu'aucun secret n'est versionné avant de publier le dépôt sur GitHub | P0 | simple | `.gitignore`, `storage/config.php` | `git ls-files storage` ne liste que `storage/.htaccess`, `storage/logs/.gitkeep`, `storage/tmp/.gitkeep` ; `git log --all -- storage/config.php` ne renvoie rien | ✅ 15/10/2026 |
| SEC-2 | Régénérer la Secret Key Mailjet, partagée par erreur dans une conversation (L1) | P0 | simple | aucun (site Mailjet + Admin → E-mails) | L'ancienne clé est supprimée dans Mailjet ; la nouvelle est saisie dans Admin → E-mails ; « Enregistrer et envoyer un test » → e-mail reçu | ⬜ |
| SEC-3 | Ajouter une politique CSP au site public, comme dans l'admin (L2) | P1 | simple | `app/bootstrap.php`, `app/layout.php`, `tests/http_test.php` | F12 → Réseau → l'en-tête `Content-Security-Policy` est présent sur Accueil, Produits et Contact ; le menu mobile et la recherche fonctionnent ; la console n'affiche aucune erreur ; un test automatique le vérifie | ✅ 15/10/2026 |
| SEC-4 | Rendre le temps de réponse de la connexion identique, que l'adresse existe ou non (L5) | P2 | moyen | `admin/login.php` | `password_verify()` est appelé même pour une adresse inconnue (avec un hachage factice) ; les tests restent verts | ⬜ |
| SEC-5 | Fermer les autres sessions après un changement ou une réinitialisation du mot de passe (L6) | P2 | complexe | `app/schema.sql`, `app/install.php`, `app/bootstrap.php`, `admin/password.php`, `admin/reset.php` | Connecté dans deux navigateurs, vous changez le mot de passe dans le premier : au clic suivant, le second est renvoyé vers la page de connexion | ⬜ |
| SEC-6 | Ajouter un verrou à la limitation des tentatives (L4) | P2 | complexe | `app/bootstrap.php` | `throttle_fail()` lit et écrit dans une transaction avec `SELECT … FOR UPDATE` ; le commentaire `ponytail:` est retiré ; les tests restent verts | ⬜ |

**Termes** — *Secret Key* : mot de passe d'accès à Mailjet ; quiconque la possède peut envoyer des e-mails en votre nom. *CSP* (Content Security Policy) : en-tête HTTP qui dit au navigateur quels scripts et styles il a le droit d'exécuter ; il limite les dégâts d'une faille XSS (injection de code dans une page). *Hachage factice* : un faux hachage calculé une fois, pour que la vérification prenne le même temps dans tous les cas. *Transaction / verrou* : mécanisme MySQL qui empêche deux requêtes simultanées de modifier la même ligne en même temps.

---

## 2. Conformité / RGPD

| # | Action | Prio | Complexité | Fichiers concernés | Critère d'acceptation | État |
|---|---|---|---|---|---|---|
| RGPD-1 | Informer le visiteur, sous le formulaire de contact, de l'usage de ses données (L8) | P0 | simple | `contact.php`, `style.css`, `tests/http_test.php` | Sous le bouton « Envoyer », un court texte indique : à quoi servent les données, qui les reçoit, combien de temps elles sont gardées, comment demander leur suppression ; un test automatique vérifie sa présence | ✅ 15/10/2026 |
| RGPD-2 | Créer une page « Mentions légales et confidentialité » reliée au pied de page (L9) | P1 | simple | `mentions-legales.php` (nouveau), `app/site.php`, `tests/http_test.php` | Le lien apparaît dans le pied de page des 3 pages ; la page utilise `site_header()` / `site_footer()` ; elle précise que l'entreprise est fictive (TP) ; un test vérifie le code 200 | ✅ 15/10/2026 |
| RGPD-3 | Supprimer automatiquement les journaux de plus de 12 mois (L10) | P1 | moyen | `admin/index.php`, `contact.php` (texte RGPD-1), `tests/http_test.php` | À l'ouverture du tableau de bord, une entrée datée de 13 mois disparaît, une entrée de 11 mois reste ; la suppression est journalisée ; le texte de RGPD-1 annonce « 12 mois » | ✅ 15/10/2026 |
| RGPD-4 | Proposer d'exclure les messages de contact de la sauvegarde JSON (L11) | P2 | simple | `admin/maintenance.php`, `tests/http_test.php` | Case « Exclure les messages de contact » : cochée, le fichier téléchargé ne contient aucune entrée de type `contact` | ⬜ |

**Termes** — *RGPD* (Règlement général sur la protection des données) : loi européenne qui oblige à informer les personnes quand on collecte leurs données (nom, e-mail…) et à ne pas les garder indéfiniment. *Durée de conservation* : temps maximal pendant lequel une donnée personnelle est gardée. Sous WAMP il n'y a pas de tâche planifiée : la suppression est donc lancée quand l'administrateur ouvre le tableau de bord.

---

## 3. Stockage des données

| # | Action | Prio | Complexité | Fichiers concernés | Critère d'acceptation | État |
|---|---|---|---|---|---|---|
| DATA-1 | Ne plus couper les messages de contact à 2000 caractères dans les journaux (L16) | P1 | simple | `app/bootstrap.php` (`log_event()`), `tests/http_test.php` | Un message de 4000 caractères envoyé depuis Contact apparaît en entier dans Maintenance → Journaux ; un test le vérifie | ✅ 15/10/2026 |
| DATA-2 | Bloquer l'accès web à `products.json`, qui ne sert qu'à l'installation (L14) | P2 | simple | `.htaccess` | `http://localhost/infoleader/products.json` → erreur 403 ; `php app/install.php` fonctionne toujours (il lit le fichier sur le disque, pas par le web) | ⬜ |

---

## 4. Qualité du code

| # | Action | Prio | Complexité | Fichiers concernés | Critère d'acceptation | État |
|---|---|---|---|---|---|---|
| CODE-1 | Nettoyer `contact.php` : remplacer le retour chariot invisible et les sauts de ligne bruts par `"\r"` et `"\n"`, harmoniser le style avec le reste du code (L17) | P1 | simple | `contact.php` | `file contact.php` n'affiche plus « with CR » ; l'e-mail reçu a le même contenu qu'avant ; les tests restent verts | ✅ 15/10/2026 |
| CODE-2 | Afficher un message clair quand WAMP n'est pas démarré au lancement des tests (L18) | P1 | simple | `tests/http_test.php` | WAMP arrêté → « Apache ou MySQL ne répond pas : démarrez WAMP » et code de sortie 1 ; WAMP démarré → 0 échec | ⬜ |
| CODE-3 | Corriger les commentaires obsolètes (« 4 modules » dans `admin/index.php`, « scrollIntoView(pourcent) » dans `script.js`) (L21) | P2 | simple | `admin/index.php`, `script.js` | Les commentaires décrivent le code réel ; aucun changement de comportement | ⬜ |
| CODE-4 | Supprimer, en fin de test, les journaux créés par les tests (L19) | P2 | simple | `tests/http_test.php` | Le nombre de lignes de la table `logs` est le même avant et après `php tests/http_test.php` | ⬜ |

---

## 5. Front-end

| # | Action | Prio | Complexité | Fichiers concernés | Critère d'acceptation | État |
|---|---|---|---|---|---|---|
| FRONT-1 | Ajouter un thème sombre automatique (L22) | P2 | moyen | `style.css` | Windows en mode sombre → le site s'affiche en sombre et reste lisible ; en mode clair, rien ne change ; seules les variables de `:root` sont redéfinies | ⬜ |
| FRONT-2 | Respecter le réglage « réduire les animations » dans le défilement fluide, ou supprimer cette fonction inutilisée (L23) | P2 | simple | `script.js` | Aucun défilement animé quand « Afficher les animations » est désactivé dans Windows ; le reste du script fonctionne | ⬜ |
| FRONT-3 | Ajouter un lien « Aller au contenu » pour la navigation au clavier (L24) | P2 | simple | `app/site.php`, `app/layout.php`, `style.css`, `index.php` | Touche Tab au chargement → le lien apparaît en premier ; Entrée → le focus passe au contenu principal | ⬜ |
| FRONT-4 | Créer une page d'erreur 404 aux couleurs du site (L24) | P2 | simple | `.htaccess`, `404.php` (nouveau) | `http://localhost/infoleader/page-inexistante` → page InfoLeader avec un lien vers l'accueil, code HTTP 404 (F12 → Réseau) | ⬜ |
| FRONT-5 | Remplacer les 6 images provisoires du TP1 par de vraies photos (L25) | P2 | simple | aucun (Admin → Articles) | Chaque article a une image réelle, JPEG/PNG/WebP de 2 Mo maximum | ⬜ |

**Termes** — *`prefers-color-scheme`* : règle CSS qui détecte si Windows est en mode clair ou sombre. *Lien d'évitement* : lien caché qui apparaît au clavier et permet de sauter le menu. *404* : code HTTP « page introuvable ».

---

## 6. Documentation

| # | Action | Prio | Complexité | Fichiers concernés | Critère d'acceptation | État |
|---|---|---|---|---|---|---|
| DOC-1 | Mettre à jour `README.md` et `ETAT-DU-PROJET.md` (L21) | P1 | simple | `README.md`, `ETAT-DU-PROJET.md` | Plus de phrase « le formulaire de contact n'envoie toujours pas d'e-mail » ; le nombre de vérifications annoncé est celui affiché par `php tests/http_test.php` | ⬜ |
| DOC-2 | Tenir `DOCUMENTATION.md` et `TODO_LIST.md` à jour après chaque tâche, puis les redéposer sur GitHub | P1 | simple | `DOCUMENTATION.md`, `TODO_LIST.md` | Chaque tâche terminée est marquée ✅ avec sa date ; la limite correspondante de `DOCUMENTATION.md` est retirée ou mise à jour | ⬜ (continu) |

---

## 7. Exploitation locale (WAMP)

| # | Action | Prio | Complexité | Fichiers concernés | Critère d'acceptation | État |
|---|---|---|---|---|---|---|
| EXPL-1 | Mettre en place une sauvegarde SQL restaurable avec `mysqldump` (fourni avec WAMP) et documenter la restauration (L12) | P1 | simple | `README.md` (procédure) | Un fichier `.sql` est créé **hors** de `C:\wamp\www` ; importé dans une base vide `infoleader_restore` via phpMyAdmin, il redonne les 6 tables et leurs données | ✅ 15/10/2026 |
| EXPL-2 | Donner un mot de passe au compte MySQL `root` de WAMP (L3) | P1 | simple | aucun (phpMyAdmin → Comptes d'utilisateurs) | phpMyAdmin refuse `root` sans mot de passe ; le site fonctionne toujours (il utilise le compte `infoleader`) ; `php app/install.php --root-pass=…` fonctionne | ⬜ |
| EXPL-3 | Passer `display_errors` à `Off` pour Apache dans WAMP (L7) | P2 | simple | aucun (menu WAMP → PHP → Paramètres PHP) | Une erreur de syntaxe volontaire dans un fichier de test affiche une page vide ou une erreur 500, sans chemin de fichier ; le fichier de test est ensuite supprimé | ⬜ |
| EXPL-4 | Permettre de vider le log SMTP de débogage depuis Maintenance (L13) | P2 | simple | `admin/maintenance.php`, `tests/http_test.php` | Bouton avec case de confirmation ; après purge, la ligne « Log de débogage SMTP » de l'onglet Système indique « vide » | ⬜ |

**Termes** — *`mysqldump`* : programme livré avec MySQL (`C:\wamp\bin\mysql\mysql8.4.7\bin\mysqldump.exe`) qui écrit toute la base dans un fichier `.sql`. Ce fichier contient les hachages de mot de passe et la clé SMTP chiffrée : rangez-le hors du dossier `www` et ne le publiez jamais. *`display_errors`* : réglage PHP qui affiche les erreurs dans la page ; utile pour déboguer, risqué car il révèle les chemins des fichiers.

---

## Définition de Terminé

Une tâche est **terminée** seulement si **tout** ce qui suit est vrai :

1. Le critère d'acceptation de la tâche est vérifié **dans le navigateur**, sur `http://localhost/infoleader/`.
2. `php tests/http_test.php` affiche **0 échec**, et une vérification a été ajoutée pour la nouvelle fonctionnalité quand c'est possible.
3. Les règles de sécurité de `AGENTS.md` sont respectées : jeton CSRF dans chaque formulaire, `e()` sur chaque affichage, requêtes préparées, pas de script ni de style en ligne dans l'admin.
4. Aucune nouvelle dépendance, aucun fichier hors de `C:\wamp\www\infoleader\` (sauf les sauvegardes SQL, volontairement rangées ailleurs).
5. Aucun secret dans Git : `git status` ne montre ni `storage/config.php`, ni clé, ni sauvegarde `.sql`.
6. Le code modifié est commenté en français, comme le reste du projet.
7. La tâche est marquée ✅ avec la date dans ce fichier, et `DOCUMENTATION.md` est mis à jour si le comportement du site change.
8. Un commit Git au message clair enregistre la tâche (une tâche = un commit).

---

## Séquencement par jalons

Traitez les jalons dans l'ordre. À l'intérieur d'un jalon, une tâche à la fois, testée avant de passer à la suivante.

| Jalon | Objectif | Tâches | Priorité |
|---|---|---|---|
| **0 — Avant la publication sur GitHub** | Ne publier aucun secret | SEC-1 ✅ | P0 |
| **1 — Urgent** | Supprimer le risque de la clé exposée, respecter l'obligation d'information | SEC-2, RGPD-1 ✅ | P0 |
| **2 — Sécurité et conformité** | Renforcer le site public et la gestion des données personnelles | SEC-3 ✅, RGPD-2 ✅, RGPD-3 ✅, EXPL-2 | P1 |
| **3 — Fiabilité** | Pouvoir restaurer la base, fiabiliser le code et les tests, documentation juste | EXPL-1 ✅, DATA-1 ✅, CODE-1 ✅, CODE-2, DOC-1 | P1 |
| **4 — Améliorations rapides** | Petits gains de confort, d'accessibilité et de propreté | DATA-2, CODE-3, CODE-4, FRONT-2, FRONT-3, FRONT-4, FRONT-5, EXPL-3, EXPL-4, RGPD-4 | P2 |
| **5 — Pour aller plus loin** | Tâches plus longues, si le temps le permet | FRONT-1, SEC-4, SEC-5, SEC-6 | P2 |
| **En continu** | Garder la documentation fidèle au code | DOC-2 | P1 |
