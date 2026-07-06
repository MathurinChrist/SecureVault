<?php

namespace App\Service;

use App\Entity\Vault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Envelope encryption for vaults — the single key scheme in the app.
 *
 * Each vault owns a random 256-bit data-encryption key (DEK). Password entries are
 * encrypted with that DEK. The DEK itself is never stored in plaintext: it is wrapped
 * (encrypted) with a server master key and stored on the vault as `encryptedKey`.
 *
 * The master key is derived from the VAULT_ENCRYPTION_KEY secret with HKDF, keyed by a
 * version number recorded per vault (`keyEncryptionVersion`). Bumping the version lets the
 * master key be rotated by re-wrapping DEKs — the bulk ciphertext (the entries) never has
 * to be re-encrypted. Production rotation should introduce fresh secret material for the
 * new version; the HKDF domain separation here is the mechanism, not a substitute for it.
 *
 * Because the server can unwrap any vault's DEK, share recipients, password resets, and the
 * offline leak-check command all work without the owner's password being present.
 */
class VaultKeyProvider
{
    /** Master-key version applied to newly created vault keys. */
    public const CURRENT_KEY_VERSION = 1;

    public function __construct(
        #[Autowire(env: 'VAULT_ENCRYPTION_KEY')]
        private readonly string $masterKeySecret,
        private readonly EncryptionService $encryptionService,
        private readonly EntityManagerInterface $em,
    ) {}

    private function masterKey(int $version): string
    {
        return hash_hkdf('sha256', $this->masterKeySecret, 32, 'securevault-vault-master-v' . $version);
    }

    /**
     * Returns the vault's plaintext DEK, generating and persisting a wrapped one on first use.
     */
    public function getOrCreateKey(Vault $vault): string
    {
        if ($vault->getEncryptedKey() !== null) {
            return $this->encryptionService->decrypt(
                $vault->getEncryptedKey(),
                $this->masterKey($vault->getKeyEncryptionVersion())
            );
        }

        $dek = random_bytes(32);
        $vault->setKeyEncryptionVersion(self::CURRENT_KEY_VERSION);
        $vault->setEncryptedKey(
            $this->encryptionService->encrypt($dek, $this->masterKey(self::CURRENT_KEY_VERSION))
        );
        $this->em->flush();

        return $dek;
    }
}
