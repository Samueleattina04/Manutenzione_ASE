<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Invio di notifiche Web Push (VAPID + payload cifrato aes128gcm) in PHP puro,
 * senza dipendenze esterne: usa solo openssl e hash_hkdf.
 *
 * Riferimenti: RFC 8291 (Message Encryption for Web Push), RFC 8188
 * (aes128gcm), draft VAPID.
 */
class WebPush
{
    /** Prefisso ASN.1 di una chiave pubblica P-256 (SubjectPublicKeyInfo). */
    private const P256_SPKI_PREFIX = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

    /** Le notifiche push sono configurate (chiavi VAPID presenti)? */
    public static function enabled(): bool
    {
        return config('webpush.public_key') && config('webpush.private_key');
    }

    /** Chiave pubblica VAPID (base64url del punto grezzo da 65 byte) per il browser. */
    public static function publicKey(): string
    {
        return (string) config('webpush.public_key');
    }

    /**
     * Invia una notifica a una subscription. Ritorna lo status HTTP del push
     * service (2xx = accettata; 404/410 = subscription non più valida), oppure
     * 0 in caso di errore locale/rete.
     */
    public static function send(string $endpoint, string $p256dh, string $auth, string $payload, int $ttl = 2419200): int
    {
        if (! self::enabled()) {
            return 0;
        }

        try {
            $uaPub = self::b64uDecode($p256dh);   // 65 byte
            $authSecret = self::b64uDecode($auth); // 16 byte
            $body = self::encryptPayload($payload, $uaPub, $authSecret);

            $headers = [
                'Content-Encoding' => 'aes128gcm',
                'TTL' => (string) $ttl,
                'Urgency' => 'high',
                'Authorization' => self::vapidAuthorization($endpoint),
            ];

            $resp = Http::withHeaders($headers)
                ->timeout(10)
                ->withBody($body, 'application/octet-stream')
                ->post($endpoint);

            return $resp->status();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** Genera una coppia di chiavi VAPID (per il comando di setup). */
    public static function generateVapidKeys(): array
    {
        $key = self::newEcKey();
        openssl_pkey_export($key, $pem);

        return [
            'public' => self::b64uEncode(self::rawPublicFromKey($key)),
            'private_b64' => base64_encode($pem),
        ];
    }

    // --- crittografia payload (RFC 8291 / RFC 8188) --------------------------

    private static function encryptPayload(string $plaintext, string $uaPubRaw, string $authSecret): string
    {
        $as = self::newEcKey();
        $asPubRaw = self::rawPublicFromKey($as);
        $uaPubKey = openssl_pkey_get_public(self::rawPublicToPem($uaPubRaw));
        $ecdh = openssl_pkey_derive($uaPubKey, $as, 32);
        if ($ecdh === false) {
            throw new \RuntimeException('ECDH non riuscita');
        }

        $salt = random_bytes(16);
        [$cek, $nonce] = self::deriveKeys($ecdh, $authSecret, $uaPubRaw, $asPubRaw, $salt);

        $header = $salt.pack('N', 4096).chr(strlen($asPubRaw)).$asPubRaw;
        $tag = '';
        $cipher = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        return $header.$cipher.$tag;
    }

    /** Deriva CEK (16) e NONCE (12) come da RFC 8291 + RFC 8188. */
    private static function deriveKeys(string $ecdh, string $auth, string $uaPub, string $asPub, string $salt): array
    {
        $keyInfo = "WebPush: info\x00".$uaPub.$asPub;
        $ikm = hash_hkdf('sha256', $ecdh, 32, $keyInfo, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        return [$cek, $nonce];
    }

    // --- VAPID (RFC 8292) ---------------------------------------------------

    private static function vapidAuthorization(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $aud = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        $header = self::b64uEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64uEncode(json_encode([
            'aud' => $aud,
            'exp' => time() + 12 * 3600,
            'sub' => (string) config('webpush.subject'),
        ]));
        $signingInput = $header.'.'.$claims;

        $pem = base64_decode((string) config('webpush.private_key'));
        openssl_sign($signingInput, $der, $pem, OPENSSL_ALGO_SHA256);
        $jwt = $signingInput.'.'.self::b64uEncode(self::derToRawSignature($der));

        return 'vapid t='.$jwt.', k='.self::publicKey();
    }

    /** Converte una firma ECDSA DER in R||S grezzo da 64 byte. */
    private static function derToRawSignature(string $der): string
    {
        $off = 2; // salta SEQUENCE + lunghezza (sempre < 128 per P-256)
        $rs = [];
        for ($i = 0; $i < 2; $i++) {
            $off++; // 0x02 (INTEGER)
            $len = ord($der[$off++]);
            $val = ltrim(substr($der, $off, $len), "\x00");
            $rs[] = str_pad($val, 32, "\0", STR_PAD_LEFT);
            $off += $len;
        }

        return $rs[0].$rs[1];
    }

    // --- utility EC / base64url ---------------------------------------------

    private static function newEcKey()
    {
        return openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    private static function rawPublicFromKey($key): string
    {
        $d = openssl_pkey_get_details($key);

        return "\x04".str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    private static function rawPublicToPem(string $raw): string
    {
        $der = self::P256_SPKI_PREFIX.$raw;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function b64uEncode(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function b64uDecode(string $d): string
    {
        return base64_decode(strtr($d, '-_', '+/').str_repeat('=', (4 - strlen($d) % 4) % 4));
    }
}
