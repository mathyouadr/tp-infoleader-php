// ============================================================
// script.js — Interactions vanilla JS (aucune dépendance)
// Chargé via une balise <script> en fin de <body> sur chaque page.
// Menu mobile, filtre produits, messages, confirmations.
// ============================================================

document.addEventListener("DOMContentLoaded", function () {

    /* --------------------------------------------------------
     * 1) Menu mobile (hamburger)
     * Le bouton affiche/masque le menu sur petits écrans.
     * ------------------------------------------------------ */
    function initMobileMenu() {
        var toggle = document.getElementById("menu-toggle");
        var navLinks = document.querySelector(".navbar__links");

        // Aucun bouton ou menu présent : on s'arrête là
        if (!toggle || !navLinks) {
            return;
        }

        // Bascule ouverte / fermée au clic
        toggle.addEventListener("click", function () {
            var isOpen = navLinks.classList.toggle("is-open");
            toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });

        // Ferme le menu après le clic sur un lien
        navLinks.addEventListener("click", function (event) {
            if (event.target.tagName === "A") {
                navLinks.classList.remove("is-open");
                toggle.setAttribute("aria-expanded", "false");
            }
        });
    }

    /* --------------------------------------------------------
     * 2) Filtre de produits en temps réel (optimisé)
     * Optimisations :
     *   - les données (nom, description) sont lues une seule fois
     *   - debounce : les manipulations du DOM sont espacées
     * ------------------------------------------------------ */
    function initProductFilter() {
        var searchInput = document.getElementById("product-search");
        var productGrid = document.getElementById("products-grid");
        var count = document.getElementById("products-count");
        var empty = document.getElementById("products-no-match");

        // Page non concernée : on s'arrête là
        if (!searchInput || !productGrid) {
            return;
        }

        // Cache : on extrait nom + description de chaque carte
        // une seule fois, pour éviter de relire le DOM à chaque saisie
        var cards = Array.prototype.slice.call(
            productGrid.querySelectorAll(".product-card")
        ).map(function (card) {
            return {
                element: card,
                name: card.getAttribute("data-name").toLowerCase(),
                description: card.getAttribute("data-description").toLowerCase()
            };
        });

        // Calcule la liste des cartes à conserver pour la saisie
        function applyFilter() {
            var query = searchInput.value.toLowerCase().trim();
            var visible = cards.filter(function (card) {
                return card.name.indexOf(query) !== -1 ||
                    card.description.indexOf(query) !== -1;
            });

            // Reconstruction de la grille en une seule passe
            productGrid.innerHTML = "";
            visible.forEach(function (card) {
                productGrid.appendChild(card.element);
            });

            // Compteur + message quand aucun article ne correspond
            if (count) {
                count.textContent = visible.length + (visible.length > 1 ? " articles" : " article");
            }
            if (empty) {
                empty.hidden = visible.length > 0;
            }
        }

        // Debounce : n'exécute le filtre que 150 ms après
        // la dernière frappe (performance)
        function debounce(fn, delay) {
            var timer;
            return function () {
                clearTimeout(timer);
                timer = setTimeout(fn, delay);
            };
        }

        searchInput.addEventListener("input", debounce(applyFilter, 150));
    }

    /* --------------------------------------------------------
     * 3) Messages temporaires : disparaissent après 5,5 s
     * ------------------------------------------------------ */
    function initAutoHide() {
        var alerts = document.querySelectorAll(".alert[data-autohide]");
        Array.prototype.forEach.call(alerts, function (alert) {
            setTimeout(function () {
                alert.classList.add("is-hiding");
                setTimeout(function () {
                    alert.remove();
                }, 500);
            }, 5500);
        });
    }

    /* --------------------------------------------------------
     * 4) Compte à rebours sur le bouton après un échec
     * (confort uniquement : la vraie limite est côté serveur)
     * ------------------------------------------------------ */
    function initWaitButton() {
        var form = document.querySelector("form[data-wait]");
        if (!form) {
            return;
        }
        var button = form.querySelector("button[type=submit]");
        var label = button.textContent;
        var remaining = parseInt(form.getAttribute("data-wait"), 10);

        function tick() {
            if (remaining <= 0) {
                button.disabled = false;
                button.textContent = label;
                return;
            }
            button.disabled = true;
            button.textContent = "Patientez " + remaining + " s";
            remaining -= 1;
            setTimeout(tick, 1000);
        }
        tick();
    }

    /* --------------------------------------------------------
     * 5) Confirmation avant les actions destructrices
     * <form data-confirm="Message ?">
     * ------------------------------------------------------ */
    function initConfirm() {
        var forms = document.querySelectorAll("form[data-confirm]");
        Array.prototype.forEach.call(forms, function (form) {
            form.addEventListener("submit", function (event) {
                if (!window.confirm(form.getAttribute("data-confirm"))) {
                    event.preventDefault();
                }
            });
        });
    }

    /* --------------------------------------------------------
     * 6) Ombre sous la barre de navigation dès que la page défile
     * ------------------------------------------------------ */
    function initNavbarShadow() {
        var navbar = document.querySelector(".navbar");
        if (!navbar) {
            return;
        }
        var progress = document.querySelector(".scroll-progress");
        function update() {
            navbar.classList.toggle("is-scrolled", window.scrollY > 8);
            // Barre de progression : part de la page déjà parcourue (0 à 1)
            if (progress) {
                var max = document.documentElement.scrollHeight - window.innerHeight;
                progress.style.setProperty("--progress", max > 0 ? window.scrollY / max : 0);
            }
        }
        window.addEventListener("scroll", update, { passive: true });
        update();
    }

    /* --------------------------------------------------------
     * Initialisation de toutes les fonctionnalités
     * ------------------------------------------------------ */
    initAutoHide();
    initWaitButton();
    initConfirm();
    initMobileMenu();
    initProductFilter();
    initNavbarShadow();
});