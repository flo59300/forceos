<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Notifications push (Web Push, chiffrement aes128gcm, signature VAPID ES256), sans Composer.
 * Reprise de includes/WebPush.php (2.x) : mêmes clés VAPID, donc les téléphones abonnés avant la bascule
 * continuent de recevoir les notifications sans se réabonner.
 *
 * Différence : les envois d'un lot partent en parallèle (curl_multi) avec un délai de 10 s,
 * au lieu d'un envoi après l'autre avec 30 s chacun.
 */
class WebPush
{
    public function __construct(private string $publicKey, private string $privateKey, private string $subject)
    {
    }

    /** Clés configurées : .env (fo.vapidPublic…) puis réglages de la 2.x (vapid_public_key…). */
    public static function fromConfig(): ?self
    {
        $cfg  = config('Fo');
        $pub  = $cfg->vapidPublic ?: Settings::get('vapid_public_key');
        $priv = $cfg->vapidPrivate ?: Settings::get('vapid_private_key');
        $sub  = $cfg->vapidSubject ?: Settings::get('vapid_subject', 'mailto:contact@example.org');

        return ($pub !== '' && $priv !== '') ? new self($pub, $priv, $sub) : null;
    }

    public static function publicKeyConfigured(): string
    {
        return config('Fo')->vapidPublic ?: Settings::get('vapid_public_key');
    }

    /**
     * Envoie le même message à plusieurs abonnements.
     * @param list<array{endpoint:string, p256dh:string, auth:string}> $subs
     * @return list<array{endpoint:string, success:bool, statusCode:int}>
     */
    public function sendBatch(array $subs, string $payload, int $ttl = 86400): array
    {
        $mh      = curl_multi_init();
        $handles = [];
        $results = [];
        foreach ($subs as $i => $s) {
            $body = $this->encrypt($payload, self::b64d($s['p256dh']), self::b64d($s['auth']));
            if ($body === null || ! preg_match('#^https://#', $s['endpoint'])) {
                $results[$i] = ['endpoint' => $s['endpoint'], 'success' => false, 'statusCode' => 0];

                continue;
            }
            $ch = curl_init($s['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm',
                    'TTL: ' . $ttl, 'Urgency: normal', 'Authorization: ' . $this->vapid($s['endpoint']),
                ],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }
        do {
            $st = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $st === CURLM_OK);
        foreach ($handles as $i => $ch) {
            $code        = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $results[$i] = ['endpoint' => $subs[$i]['endpoint'], 'success' => $code >= 200 && $code < 300, 'statusCode' => $code];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        ksort($results);

        return array_values($results);
    }

    /** Chiffrement aes128gcm (RFC 8291), identique à la 2.x. */
    public function encrypt(string $payload, string $userPublicKey, string $userAuth): ?string
    {
        if (strlen($userPublicKey) !== 65 || $userPublicKey[0] !== "\x04" || $userAuth === '') {
            return null;
        }
        $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $local) {
            return null;
        }
        $d        = openssl_pkey_get_details($local);
        $localPub = "\x04" . str_pad($d['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $peer     = openssl_pkey_get_public(self::publicPem($userPublicKey));
        $shared   = '';
        if (! $peer || ! ($shared = openssl_pkey_derive($peer, $local, 32))) {
            return null;
        }
        $prk   = hash_hmac('sha256', $shared, $userAuth, true);
        $ikm   = self::hkdf($prk, "WebPush: info\x00" . $userPublicKey . $localPub, 32);
        $salt  = random_bytes(16);
        $prk2  = hash_hmac('sha256', $ikm, $salt, true);
        $cek   = self::hkdf($prk2, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdf($prk2, "Content-Encoding: nonce\x00", 12);
        $tag   = '';
        $enc   = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($enc === false) {
            return null;
        }

        return $salt . pack('N', 4096) . chr(65) . $localPub . $enc . $tag;
    }

    private static function hkdf(string $prk, string $info, int $len): string
    {
        $t = $out = '';
        for ($i = 1; strlen($out) < $len; $i++) {
            $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $out .= $t;
        }

        return substr($out, 0, $len);
    }

    private static function publicPem(string $point): string
    {
        $bits = "\x03" . chr(strlen($point) + 1) . "\x00" . $point;
        $oid  = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $der  = "\x30" . chr(strlen($oid) + strlen($bits)) . $oid . $bits;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** En-tête Authorization VAPID (JWT ES256, valable 12 h). */
    public function vapid(string $endpoint): string
    {
        $p   = parse_url($endpoint);
        $h   = self::b64e(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $c   = self::b64e(json_encode(['aud' => $p['scheme'] . '://' . $p['host'], 'exp' => time() + 43200, 'sub' => $this->subject]));
        $in  = $h . '.' . $c;
        $key = openssl_pkey_get_private($this->privatePem());
        if (! $key) {
            throw new RuntimeException('Clé privée VAPID invalide');
        }
        openssl_sign($in, $sig, $key, OPENSSL_ALGO_SHA256);

        return 'vapid t=' . $in . '.' . self::b64e(self::derToRaw($sig)) . ', k=' . $this->publicKey;
    }

    private function privatePem(): string
    {
        $priv = self::b64d($this->privateKey);
        $pub  = self::b64d($this->publicKey);
        $oid  = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $bits = "\x03" . chr(strlen($pub) + 1) . "\x00" . $pub;
        $seq  = "\x02\x01\x01\x04" . chr(strlen($priv)) . $priv . "\xa0" . chr(strlen($oid)) . $oid . "\xa1" . chr(strlen($bits)) . $bits;

        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode("\x30" . chr(strlen($seq)) . $seq), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    private static function derToRaw(string $der): string
    {
        $pos  = 3;
        $rLen = ord($der[$pos++]);
        $r    = substr($der, $pos, $rLen);
        $pos += $rLen + 1;
        $sLen = ord($der[$pos++]);
        $s    = substr($der, $pos, $sLen);

        return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT) . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    public static function generateKeys(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($k);

        return [
            'publicKey' => self::b64e("\x04" . str_pad($d['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\x00", STR_PAD_LEFT)),
            'privateKey' => self::b64e(str_pad($d['ec']['d'], 32, "\x00", STR_PAD_LEFT)),
        ];
    }

    public static function b64e(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function b64d(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
