<?php

namespace App\Service;

class EncryptionService
{
    private const METHOD = 'aes-256-gcm';

    public function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::METHOD));
        $tag = '';
        $ciphertext = openssl_encrypt($data, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encryptedData, string $key): string
    {
        $decoded = base64_decode($encryptedData);
        $ivLen = openssl_cipher_iv_length(self::METHOD);
        $iv = substr($decoded, 0, $ivLen);
        $tag = substr($decoded, $ivLen, 16);
        $ciphertext = substr($decoded, $ivLen + 16);

        $result = openssl_decrypt($ciphertext, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($result === false) {
            throw new \RuntimeException('Decryption failed: invalid ciphertext or key.');
        }

        return $result;
    }
}
