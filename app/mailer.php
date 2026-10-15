<?php
/* ============================================================
   Envoi d'e-mails : PHP mail() ou SMTP Mailjet via PHPMailer.
   La configuration est stockée dans la table settings (clé
   « mail ») ; le mot de passe SMTP y est chiffré (AES-256-GCM).
   ============================================================ */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

const MAIL_DEBUG_LOG = STORAGE . '/logs/smtp-debug.log';

/** Configuration courante, complétée par les valeurs par défaut Mailjet */
function mail_config(): array
{
    return get_setting('mail') + [
        'transport' => 'smtp',          // 'smtp' (Mailjet) ou 'phpmail'
        'from_name' => 'InfoLeader',
        'from_email' => '',
        'to_email' => '',
        'smtp_host' => 'in-v3.mailjet.com',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',         // tls = STARTTLS, ssl = SMTPS, none
        'smtp_user' => '',
        'smtp_pass' => '',              // chiffré
        'debug' => false,
    ];
}

/** Indique si l'envoi est utilisable (champs obligatoires remplis) */
function mail_ready(?array $c = null): bool
{
    $c ??= mail_config();
    if ($c['from_email'] === '') {
        return false;
    }
    return $c['transport'] === 'phpmail' || ($c['smtp_user'] !== '' && $c['smtp_pass'] !== '');
}

/**
 * Envoie un e-mail texte. Renvoie null si succès, sinon le message d'erreur.
 * $replyTo : adresse utilisée par « Répondre » (ex. visiteur du formulaire de contact).
 * Les identifiants et le contenu des messages sont masqués dans le log de débogage.
 */
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): ?string
{
    $c = mail_config();
    if (!mail_ready($c)) {
        $error = 'La configuration e-mail est incomplète.';
        log_event('event', "Échec d'envoi d'e-mail à $to : $error");
        return $error;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        if ($c['transport'] === 'smtp') {
            $mail->isSMTP();
            $mail->Host = $c['smtp_host'];
            $mail->Port = (int) $c['smtp_port'];
            $mail->SMTPAuth = true;
            $mail->Username = $c['smtp_user'];
            $mail->Password = (string) decrypt_secret($c['smtp_pass']);
            $mail->SMTPSecure = match ($c['smtp_secure']) {
                'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                'tls' => PHPMailer::ENCRYPTION_STARTTLS,
                default => '',
            };
            $mail->SMTPAutoTLS = $c['smtp_secure'] !== 'none';
            $mail->Timeout = 15;
            if ($c['debug']) {
                $mail->SMTPDebug = 2; // dialogue client + serveur
                $mail->Debugoutput = mail_debug_writer();
            }
        } else {
            $mail->isMail();
        }

        $mail->setFrom($c['from_email'], $c['from_name']);
        $mail->addAddress($to);
        if ($replyTo !== null) {
            $mail->addReplyTo($replyTo);
        }
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return null;
    } catch (MailException $ex) {
        $error = $mail->ErrorInfo ?: $ex->getMessage();
        log_event('event', "Échec d'envoi d'e-mail à $to : $error");
        return $error;
    }
}

/** Écrit le dialogue SMTP dans storage/logs en masquant AUTH et DATA */
function mail_debug_writer(): callable
{
    $mask = null; // null, 'auth' ou 'data'
    return function (string $line) use (&$mask): void {
        $line = rtrim($line);
        if (str_contains($line, 'CLIENT -> SERVER:')) {
            if (preg_match('/CLIENT -> SERVER: AUTH (\S+)/', $line, $m)) {
                $mask = 'auth';
                $line = "CLIENT -> SERVER: AUTH {$m[1]} [masqué]";
            } elseif (preg_match('/CLIENT -> SERVER: DATA$/', $line)) {
                $mask = 'data';
            } elseif ($mask === 'data' && preg_match('/CLIENT -> SERVER: \.$/', $line)) {
                $mask = null;
            } elseif ($mask !== null) {
                $line = 'CLIENT -> SERVER: [masqué]';
            }
        } elseif ($mask === 'auth' && preg_match('/SERVER -> CLIENT: (235|535)/', $line)) {
            $mask = null;
        }
        file_put_contents(MAIL_DEBUG_LOG, date('Y-m-d H:i:s ') . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    };
}
