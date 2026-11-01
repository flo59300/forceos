<?php

namespace App\Libraries;

use Throwable;

/**
 * Envoi de courriels avec les réglages SMTP de la 2.x (table app_settings : smtp_host, smtp_port,
 * smtp_user, smtp_pass, smtp_from, smtp_from_name, smtp_encryption).
 *
 * Différences voulues avec la 2.x :
 *  - l'objet est encodé correctement (les accents et la ponctuation ne sont plus abîmés) ;
 *  - send() renvoie false en cas d'échec : l'appelant le sait, alors que la 2.x croyait l'envoi réussi.
 */
class Mailer
{
    /** Pour les tests : courriels capturés au lieu d'être envoyés. */
    public static ?array $outbox = null;

    public static function send(string $to, string $subject, string $body): bool
    {
        $to = trim(preg_replace('/[\r\n]/', '', $to));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (self::$outbox !== null || env('fo.mailCapture')) {
            self::$outbox[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
            log_message('info', "Courriel capturé pour {$to} : {$subject}\n{$body}");

            return true;
        }

        try {
            $host  = Settings::get('smtp_host');
            $user  = Settings::get('smtp_user');
            $from  = preg_replace('/[\r\n]/', '', Settings::get('smtp_from', $user ?: 'noreply@fo-gestion.fr'));
            $name  = preg_replace('/[\r\n]/', '', Settings::get('smtp_from_name', Settings::appName()));
            $email = service('email', null, false);
            $cfg   = [
                'mailType' => 'text', 'charset' => 'UTF-8', 'wordWrap' => false,
                'protocol' => ($host !== '' && $user !== '') ? 'smtp' : 'mail',
            ];
            if ($cfg['protocol'] === 'smtp') {
                $enc = Settings::get('smtp_encryption', 'tls');
                $cfg += [
                    'SMTPHost' => $host, 'SMTPUser' => $user, 'SMTPPass' => Settings::get('smtp_pass'),
                    'SMTPPort' => (int) Settings::get('smtp_port', '587'), 'SMTPTimeout' => 10,
                    'SMTPCrypto' => in_array($enc, ['tls', 'ssl'], true) ? $enc : '',
                ];
            }
            $email->initialize($cfg);
            $email->setFrom($from, $name);
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage($body);
            $ok = $email->send(false);
            if (! $ok) {
                log_message('error', 'Courriel non envoyé à ' . $to . ' : ' . strip_tags($email->printDebugger(['headers'])));
            }

            return $ok;
        } catch (Throwable $e) {
            log_message('error', 'Courriel non envoyé : ' . $e->getMessage());

            return false;
        }
    }
}
