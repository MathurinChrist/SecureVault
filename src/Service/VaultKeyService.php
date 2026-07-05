<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manages per-user vault encryption keys.
 *
 * The actual key is derived at login via PBKDF2 and stored in the session only.
 * It is never persisted to the database. Without the user's plaintext password,
 * the key cannot be reconstructed — even by an admin with DB access.
 */
class VaultKeyService
{
    private const SESSION_KEY  = '_sv_vault_key';
    private const ITERATIONS   = 100_000;
    private const KEY_LENGTH   = 32;

    public function __construct(private readonly RequestStack $requestStack) {}

    public function deriveKey(string $plaintextPassword, string $salt): string
    {
        return hash_pbkdf2('sha256', $plaintextPassword, $salt, self::ITERATIONS, self::KEY_LENGTH, true);
    }

    public function generateSalt(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function storeInSession(string $key): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, base64_encode($key));
    }

    public function getFromSession(): ?string
    {
        $encoded = $this->requestStack->getSession()->get(self::SESSION_KEY);
        return $encoded ? base64_decode($encoded) : null;
    }

    public function clearSession(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
