<?php
/* ============================================
   Page "Mentions légales et confidentialité"
   Entreprise fictive (TP) : informations légales
   et usage réel des données personnelles (RGPD).
   ============================================ */

require __DIR__ . '/app/bootstrap.php';
require ROOT . '/app/site.php';

site_header('Mentions légales', 'Mentions légales et politique de confidentialité du site InfoLeader.');
?>

    <!-- ====== En-tête de page ====== -->
    <header class="page-header">
        <div class="page-header__inner">
            <h1>Mentions légales et confidentialité</h1>
            <p class="page-header__sub">
                Qui publie ce site, et ce qu'il fait de vos données.
            </p>
        </div>
    </header>

    <main class="legal-wrap" id="contenu">

        <!-- ====== Éditeur et hébergement ====== -->
        <section>
            <h2>Éditeur du site</h2>
            <p>
                InfoLeader est une <strong>entreprise fictive</strong>, inventée pour un travail pratique
                de BTS SIO. Elle n'a aucune existence juridique et aucune activité commerciale :
                rien n'est vendu sur ce site.
            </p>
        </section>

        <section>
            <h2>Hébergement</h2>
            <p>
                Le site n'est pas publié sur Internet. Il fonctionne uniquement en local,
                sur un serveur WAMP (Apache, PHP, MySQL) installé sur le poste de l'étudiant.
            </p>
        </section>

        <section>
            <h2>Propriété intellectuelle</h2>
            <p>
                Les textes et visuels du site ont été réalisés pour le TP. Les marques citées
                dans le catalogue appartiennent à leurs propriétaires respectifs.
            </p>
        </section>

        <!-- ====== Données personnelles (RGPD) ====== -->
        <section id="donnees">
            <h2>Données personnelles</h2>
            <p>Le site collecte uniquement les données suivantes.</p>
            <ul>
                <li>
                    <strong>Formulaire de contact</strong> : votre nom, votre adresse e-mail, votre message,
                    ainsi que l'adresse IP et la date d'envoi. Ces données servent uniquement à répondre
                    à votre demande. Elles sont envoyées par e-mail à l'équipe InfoLeader et gardées dans
                    les journaux de l'espace d'administration.
                </li>
                <li>
                    <strong>Journaux de sécurité</strong> : l'adresse IP et la date des connexions à
                    l'administration et des envois du formulaire. Elles servent à bloquer les tentatives
                    répétées (protection contre les robots et le piratage de mot de passe).
                </li>
            </ul>
            <p>
                <strong>Durée de conservation</strong> : ces données sont gardées 12 mois au maximum,
                puis supprimées automatiquement. L'administrateur peut aussi les supprimer plus tôt,
                depuis l'outil Maintenance du site.
            </p>
            <p>
                <strong>Partage</strong> : vos données ne sont ni vendues ni transmises à des tiers.
                Seul le serveur d'envoi d'e-mails configuré par l'administrateur les achemine.
            </p>
            <p>
                <strong>Vos droits</strong> : vous pouvez demander à consulter, corriger ou supprimer
                vos données, ou vous opposer à leur utilisation, en écrivant via le
                <a href="contact.php">formulaire de contact</a>. Vous pouvez aussi adresser une réclamation
                à la CNIL (<a href="https://www.cnil.fr">www.cnil.fr</a>).
            </p>
        </section>

        <!-- ====== Cookies ====== -->
        <section>
            <h2>Cookies</h2>
            <p>
                Le site dépose un seul cookie, <code>INFOLEADER_SID</code>. C'est un cookie de session,
                indispensable au fonctionnement : il protège les formulaires contre les envois frauduleux
                et maintient la connexion à l'administration. Il est effacé à la fermeture du navigateur.
            </p>
            <p>Aucun cookie publicitaire ni de mesure d'audience n'est utilisé.</p>
        </section>

        <p class="legal-wrap__date">Dernière mise à jour : 15/10/2026.</p>

    </main>

<?php site_footer();
