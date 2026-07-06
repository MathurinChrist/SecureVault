<?php

namespace App\Service;

use App\Entity\Vault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a random symmetric key per vault, wrapped with the server master key.
 *
 * Unlike the per-user PBKDF2 key in VaultKeyService, this key does not depend on
 * the requester's own login/session — it can be unwrapped by the server for any
 * user granted VIEW access to the vault, which is what allows shared vaults to
 * be decrypted by recipients (see keyVersion 2 on PasswordEntry).
 */
class VaultKeyProvider
{
    public function __construct(
        #[Autowire(env: 'VAULT_ENCRYPTION_KEY')]
        private readonly string $masterKeySecret,
        private readonly EncryptionService $encryptionService,
        private readonly EntityManagerInterface $em,
    ) {}

    private function masterKey(): string
    {
        return hash('sha256', $this->masterKeySecret, true);
    }

    public function getOrCreateKey(Vault $vault): string
    {
        if ($vault->getEncryptedKey() !== null) {
            return $this->encryptionService->decrypt($vault->getEncryptedKey(), $this->masterKey());
        }

        $key = random_bytes(32);
        $vault->setEncryptedKey($this->encryptionService->encrypt($key, $this->masterKey()));
        $this->em->flush();

        return $key;
    }
}
