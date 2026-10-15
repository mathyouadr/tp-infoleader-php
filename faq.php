<?php
/* ============================================
   Page "FAQ" : questions fréquentes en accordéon.
   Balises <details>/<summary> : ouverture au clic, à la touche
   Entrée ou Espace, sans JavaScript ni base de données.
   ============================================ */

require __DIR__ . '/app/bootstrap.php';
require ROOT . '/app/site.php';

site_header('FAQ', 'Questions fréquentes sur InfoLeader : devis, conseil, prix, données personnelles.');
?>

    <header class="page-header">
        <div class="page-header__inner">
            <h1>Questions fréquentes</h1>
            <p class="page-header__sub">Les réponses aux questions que l'on nous pose le plus souvent.</p>
        </div>
    </header>

    <main class="legal-wrap" id="contenu">
        <div class="faq">
            <details class="faq__item">
                <summary>Vendez-vous en ligne ?</summary>
                <p>Non. InfoLeader présente son catalogue en ligne, mais ne vend rien sur ce site. Pour un devis, écrivez-nous via la page Contact.</p>
            </details>
            <details class="faq__item">
                <summary>Comment obtenir un devis ?</summary>
                <p>Envoyez votre demande depuis le formulaire de contact en précisant le matériel et les quantités. Nous répondons à l'adresse e-mail que vous indiquez.</p>
            </details>
            <details class="faq__item">
                <summary>Proposez-vous du conseil pour les entreprises ?</summary>
                <p>Oui. Notre équipe accompagne les entreprises et les particuliers dans le choix du matériel, l'infrastructure et le développement.</p>
            </details>
            <details class="faq__item">
                <summary>Les prix du catalogue sont-ils TTC ?</summary>
                <p>Les prix affichés sont indicatifs. Le devis que nous vous envoyons précise le montant exact.</p>
            </details>
            <details class="faq__item">
                <summary>Que faites-vous des données envoyées par le formulaire ?</summary>
                <p>Elles servent uniquement à vous répondre et sont supprimées au bout de 12 mois maximum. Le détail figure dans les mentions légales.</p>
            </details>
        </div>
        <p>Vous ne trouvez pas votre réponse ? <a href="contact.php">Contactez-nous</a>.</p>
    </main>

<?php site_footer();
