<?php

namespace App\Google;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Šifrovanje OAuth tokena u bazi (SPEC 8n.3: refreshToken šifrovan). Ključ je izveden iz APP_SECRET-a, pa promena
 * APP_SECRET-a zahteva ponovno povezivanje Google naloga.
 * libsodium (secretbox) kad je ekstenzija uključena ("s1:"), inače OpenSSL AES-256-GCM ("o1:"; XAMPP podrazumevano nema sodium).
 */
final class TokenCipher
{
    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = hash('sha256', 'kontit-google-tokens|'.$secret, true);
    }

    public function encrypt(string $plain): string
    {
        if (\function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            return 's1:'.base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
        }
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, \OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $cipher) {
            throw new \RuntimeException('Šifrovanje tokena nije uspelo.');
        }

        return 'o1:'.base64_encode($iv.$tag.$cipher);
    }

    public function decrypt(?string $encrypted): ?string
    {
        if (null === $encrypted || \strlen($encrypted) < 4) {
            return null;
        }
        $raw = base64_decode(substr($encrypted, 3), true);
        if (false === $raw) {
            return null;
        }
        if (str_starts_with($encrypted, 's1:') && \function_exists('sodium_crypto_secretbox_open') && \strlen($raw) > \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            $plain = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);

            return false === $plain ? null : $plain;
        }
        if (str_starts_with($encrypted, 'o1:') && \strlen($raw) > 28) {
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, \OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

            return false === $plain ? null : $plain;
        }

        return null;
    }
}
