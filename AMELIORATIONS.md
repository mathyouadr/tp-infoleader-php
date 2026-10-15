# AMELIORATIONS – TP 2ter

*Fork d'entraînement du site InfoLeader, réalisé le 15/10/2026.*

- **Fork local** : `C:\wamp\www\infoleader-tp2ter` → `http://localhost/infoleader-tp2ter/`
- **Fork Git** : branche `tp2ter` du dépôt `mathyouadr/tp-infoleader-php`
- **Site d'origine intact** : `C:\wamp\www\infoleader`, branche `main`, non modifié (réservé au TP 3).
- **Hors périmètre TP 3** : aucune base de données créée ni modifiée, pas de MVC, aucun module d'administration, pas d'ébénisterie, aucune dépendance ajoutée.

> Le fork réutilise la base MySQL existante du site d'origine (TP 2), sans en changer le schéma ni les données.

| # | Thème | Amélioration réalisée | Pourquoi | Fichiers modifiés | Comment vérifier |
|---|-------|-----------------------|----------|-------------------|------------------|
| 0 | Fork | Copie du site dans `infoleader-tp2ter`, chemins propres au fork (page 404, tests) | Travailler sans risque pour l'original | `.htaccess`, `tests/http_test.php` | `http://localhost/infoleader-tp2ter/page-inexistante` → page 404 du fork |
| 1 | Accessibilité | Texte alternatif « Photo : *nom de l'article* » sur les images produits (bannière décorative : `alt=""` conservé) | Un lecteur d'écran annonce l'image au lieu de l'ignorer | `app/site.php` | F12 → Éléments sur une carte produit : `alt="Photo : …"` |
| 2 | Accessibilité | Contrôle : labels `for` sur tous les champs, focus visible (`:focus-visible`), lien « Aller au contenu », contraste du texte gris `#5a6472` sur blanc ≈ 5,9:1 (> 4,5:1 exigé) | Navigation au clavier et lisibilité (WCAG AA) | — (déjà en place) | Touche Tab dès le chargement : « Aller au contenu » apparaît, chaque élément a un contour bleu |
| 3 | Responsive | Dimensions `width`/`height` sur la bannière, vérification à 375 px de toutes les pages publiques (aucun débordement horizontal) | Pas de saut de mise en page au chargement sur mobile | `index.php` | Ctrl+Maj+M (vue mobile) : pas de barre de défilement horizontale, menu ☰ fonctionnel |
| 4 | Performances | Bannière convertie en **WebP** : 1 037 Ko → **18 Ko** (PNG supprimé), `fetchpriority="high"` | Page d'accueil 50 fois plus légère | `index.php`, `assets/banniere.webp` | F12 → Réseau → recharger : `banniere.webp` ≈ 18 Ko |
| 5 | Performances | `loading="lazy"` + `decoding="async"` sur les images produits ; défilement JS inutilisé supprimé (TP 2bis, FRONT-2) | Images chargées seulement quand elles sont visibles | `app/site.php` | F12 → Réseau → filtrer « Img » sur Nos Produits en mobile : images chargées au défilement |
| 6 | SEO | Balises **Open Graph** (titre, description, URL, image, langue) sur toutes les pages publiques ; `title` et `meta description` uniques par page (y compris FAQ et 404) | Aperçu propre lors d'un partage, meilleur référencement | `app/site.php`, `404.php` | Ctrl+U sur chaque page : balises `og:*` et description différentes |
| 7 | SEO | `robots.txt` (admin exclue) et `sitemap.xml` (5 pages publiques) ; page 404 en `noindex` | Indiquer aux moteurs quoi indexer | `robots.txt`, `sitemap.xml`, `404.php` | Ouvrir `/infoleader-tp2ter/robots.txt` et `/infoleader-tp2ter/sitemap.xml` |
| 8 | UX | Message de confirmation du contact annoncé comme les autres messages (`role="status"`) ; FAQ ajoutée au menu et au pied de page, aucune page orpheline | Messages homogènes, navigation complète | `contact.php`, `app/site.php` | Envoyer le formulaire : message vert ; menu → FAQ présent sur toutes les pages |
| 9 | Fonctionnalité | Page **FAQ** en accordéon : `<details>`/`<summary>` natifs, ouverture au clic ou au clavier (Entrée/Espace), sans JavaScript ni base de données | Répondre aux questions fréquentes (devis, conseil, prix, données) | `faq.php`, `style.css`, `app/site.php` | `/infoleader-tp2ter/faq.php` : Tab sur une question puis Entrée → la réponse s'ouvre |

## Améliorations reprises de `TODO_LIST.md` (TP 2bis)

Déjà réalisées sur le site d'origine avant le fork et donc présentes ici : lien « Aller au contenu » (FRONT-3), page 404 (FRONT-4), suppression du défilement JS (FRONT-2), mentions légales (RGPD-2), CSP sur tout le site (SEC-3).

## Non traité volontairement

- **Thème sombre** : la variable `--white` sert à la fois de fond et de texte des boutons ; un thème sombre demande de revoir ~19 règles CSS.
- `robots.txt` et `sitemap.xml` ne sont pris en compte par les moteurs qu'à la racine d'un vrai domaine : ici ils servent de démonstration en local.
