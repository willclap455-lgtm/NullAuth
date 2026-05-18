<?php

declare(strict_types=1);

namespace NullAuth\Service;

final class CryptoService
{
    private string $rootKey;

    public function __construct(string $base64Key)
    {
        $decoded = base64_decode($base64Key, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            if ((string) config('app.env') === 'production') {
                throw new \RuntimeException('APP_KEY_BASE64 must be 32 random bytes encoded as base64.');
            }
            $decoded = str_repeat("\0", SODIUM_CRYPTO_KDF_KEYBYTES);
        }

        $this->rootKey = $decoded;
    }

    public function encryptString(string $plaintext, string $context): string
    {
        $dek = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $wrappedDek = $this->encryptRaw($dek, $this->kek(), 'dek:' . $context);
        $payload = $this->encryptRaw($plaintext, $dek, 'field:' . $context);
        sodium_memzero($dek);

        return json_encode([
            'v' => 1,
            'alg' => 'XCHACHA20-POLY1305',
            'kdf' => 'HKDF-SHA256',
            'wrapped_dek' => $wrappedDek,
            'payload' => $payload,
            'context' => $context,
            'aad' => hash('sha256', $context),
        ], JSON_THROW_ON_ERROR);
    }

    public function decryptString(string $envelope, string $context): string
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($envelope, true, flags: JSON_THROW_ON_ERROR);
        if (($data['v'] ?? null) !== 1 || ($data['alg'] ?? null) !== 'XCHACHA20-POLY1305') {
            throw new \RuntimeException('Unsupported encryption envelope.');
        }

        $dek = $this->decryptRaw($this->assertEnvelopePart($data['wrapped_dek'] ?? null), $this->kek(), 'dek:' . $context);
        try {
            return $this->decryptRaw($this->assertEnvelopePart($data['payload'] ?? null), $dek, 'field:' . $context);
        } finally {
            sodium_memzero($dek);
        }
    }

    public function randomBase32Secret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    private function kek(): string
    {
        return hash_hkdf('sha256', $this->rootKey, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, 'nullauth:vault:v1', 'NullAuth');
    }

    /** @return array<string, string> */
    private function encryptRaw(string $plaintext, string $key, string $aad): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);

        return [
            'nonce' => base64_encode($nonce),
            'ct' => base64_encode($ciphertext),
        ];
    }

    /** @param array<string, string> $part */
    private function decryptRaw(array $part, string $key, string $aad): string
    {
        $nonce = base64_decode($part['nonce'] ?? '', true);
        $ciphertext = base64_decode($part['ct'] ?? '', true);
        if ($nonce === false || $ciphertext === false) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $key);
        if ($plaintext === false) {
            throw new \RuntimeException('Encrypted payload authentication failed.');
        }

        return $plaintext;
    }

    /** @return array<string, string> */
    private function assertEnvelopePart(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Malformed encrypted payload.');
        }

        return $value;
    }

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }
}

