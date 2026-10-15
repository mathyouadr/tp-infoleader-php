<?php
/* ============================================
   Page "Contact"
   Le message est enregistré dans les journaux
   (Administration > Maintenance) puis envoyé par e-mail
   au destinataire configuré (Administration > E-mails).
   Anti-spam : délais progressifs puis blocage de l'IP.
   ============================================ */

require __DIR__ . '/app/bootstrap.php'; // session, CSRF, journaux
require ROOT . '/app/site.php';
require ROOT . '/app/mailer.php';

// Erreurs de validation et indicateur de succès
$errors = [];
$success = false;

// Valeurs pré-remplies (conservées après une erreur)
$nom = '';
$email = '';
$message = '';

// Traitement du formulaire uniquement en cas d'envoi POST
if (is_post()) {

    // Récupération et nettoyage des champs
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    // --- Vérification des champs requis ---
    if ($nom === '') {
        $errors[] = 'Le nom est requis.';
    }

    if ($email === '') {
        $errors[] = 'L\'email est requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Le format de l\'email est invalide.';
    }

    if ($message === '') {
        $errors[] = 'Le message est requis.';
    } elseif (mb_strlen($message) > 5000) {
        $errors[] = 'Le message est trop long (5000 caractères maximum).';
    }

    if (mb_strlen($nom) > 100) {
        $errors[] = 'Le nom est trop long (100 caractères maximum).';
    }

    // Anti-spam : chaque envoi compte (3 s, 6 s, 12 s... puis IP bloquée 15 min)
    $refusal = throttle_check('contact');
    if (!$errors && $refusal !== null) {
        $errors[] = $refusal;
    }

    // --- Enregistrement + envoi par e-mail ---
    if (!$errors) {
        throttle_fail('contact');
        // Journal (type « contact ») : le message est conservé même si l'e-mail échoue
        log_event('contact', "De $nom <$email> : $message");

        $to = mail_config()['to_email'];
        if ($to !== '') {
            // Nom sans retour à la ligne : aucune injection d'en-tête possible
            $sujet = 'InfoLeader - Message de ' . str_replace(["\r", "\n"], ' ', $nom);
            $corps = "Nouveau message reçu via le formulaire de contact.\n\n"
                . "Nom : $nom\nEmail : $email\nDate : " . date('d/m/Y à H:i') . "\n\n"
                . "$message\n\n"
                . "Répondez directement à cet e-mail pour écrire à $email.";
            send_mail($to, $sujet, $corps, $email); // un échec est noté dans les journaux
        }
        $success = true;
    }
}
site_header('Contact', 'Écrivez à InfoLeader : une question, un devis, un projet.');
?>

    <!-- ====== En-tête de page ====== -->
    <header class="page-header">
        <div class="page-header__inner">
            <h1>Contactez-nous</h1>
            <p class="page-header__sub">
                Une question, un projet ? Écrivez-nous, nous répondons rapidement.
            </p>
        </div>
    </header>

    <!-- ====== Formulaire de contact ====== -->
    <main class="contact-wrap" id="contenu">

        <?php if ($success): ?>

            <!-- Message de confirmation -->
            <div class="alert alert--success" role="status">
                Merci pour votre message — il a bien été reçu. Nous vous répondrons à <?= e($email) ?>.
            </div>
            <p><a href="products.php" class="cta">Voir nos produits</a></p>

        <?php else: ?>

            <!-- Erreurs renvoyées par PHP -->
            <?php if ($errors): ?>
                <div id="server-errors" class="alert alert--error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Validation native du navigateur (required, type=email) + contrôle serveur -->
            <form action="contact.php" method="post" class="contact-form">
                <?= csrf_field() ?>

                <div class="form-control">
                    <label for="contact-nom">Nom</label>
                    <input type="text" id="contact-nom" name="nom" value="<?= e($nom) ?>"
                           placeholder="Votre nom" maxlength="100" required autocomplete="name">
                </div>

                <div class="form-control">
                    <label for="contact-email">Email</label>
                    <input type="email" id="contact-email" name="email" value="<?= e($email) ?>"
                           placeholder="votre@email.fr" required autocomplete="email">
                </div>

                <div class="form-control">
                    <label for="contact-message">Message</label>
                    <textarea id="contact-message" name="message" rows="6" maxlength="5000"
                              placeholder="Votre message..." required><?= e($message) ?></textarea>
                </div>

                <button type="submit" class="btn-submit">Envoyer le message</button>

                <!-- Information RGPD : usage des données collectées (TODO_LIST.md, RGPD-1) -->
                <p class="form-hint form-notice">
                    Vos données (nom, e-mail, message) servent uniquement à répondre à votre demande.
                    Elles sont transmises par e-mail à l'équipe InfoLeader et conservées dans l'espace
                    d'administration du site 12 mois au maximum, puis supprimées automatiquement.
                    Elles ne sont ni vendues ni partagées. Pour les consulter ou les faire supprimer,
                    écrivez-nous via ce formulaire.
                    <a href="mentions-legales.php#donnees">En savoir plus</a>.
                </p>
            </form>

        <?php endif; ?>

    </main>

<?php site_footer();
