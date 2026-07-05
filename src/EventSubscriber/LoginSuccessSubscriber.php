<?php

namespace App\EventSubscriber;

use App\Entity\PasswordEntry;
use App\Entity\User;
use App\Repository\PasswordEntryRepository;
use App\Service\EncryptionService;
use App\Service\VaultKeyService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly VaultKeyService         $vaultKeyService,
        private readonly EncryptionService        $encryptionService,
        private readonly EntityManagerInterface   $em,
        private readonly PasswordEntryRepository  $passwordEntryRepository,
        private readonly LoggerInterface          $logger,
        private readonly string                   $sharedEncryptionKey,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getAuthenticatedToken()->getUser();

        // Google OAuth users have no plaintext password — skip key derivation
        if ($user->getGoogleId()) {
            return;
        }

        $plaintextPassword = $event->getRequest()->request->get('password', '');
        if (!$plaintextPassword) {
            return;
        }

        // Generate salt on first login if missing
        if (!$user->getEncryptionKey()) {
            $salt = $this->vaultKeyService->generateSalt();
            $user->setEncryptionKey($salt);
            $this->em->flush();
        }

        $salt       = $user->getEncryptionKey();
        $vaultKey   = $this->vaultKeyService->deriveKey($plaintextPassword, $salt);

        $this->vaultKeyService->storeInSession($vaultKey);

        // Auto-migrate legacy entries (keyVersion = 0 → encrypted with shared key)
        $this->migrateLegacyPasswords($user, $vaultKey);
    }

    private function migrateLegacyPasswords(User $user, string $vaultKey): void
    {
        $legacy = $this->passwordEntryRepository->findBy([
            'user'       => $user,
            'keyVersion' => 0,
        ]);

        if (empty($legacy)) {
            return;
        }

        $oldKey = hash('sha256', $this->sharedEncryptionKey, true);
        $count  = 0;

        foreach ($legacy as $entry) {
            try {
                $plain = $this->encryptionService->decrypt($entry->getEncryptedPassword(), $oldKey);
                $entry->setEncryptedPassword($this->encryptionService->encrypt($plain, $vaultKey));
                $entry->setKeyVersion(1);
                $count++;
            } catch (\RuntimeException $e) {
                $this->logger->warning('Failed to migrate password entry #{id}: {msg}', [
                    'id'  => $entry->getId(),
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            $this->em->flush();
            $this->logger->info('Migrated {n} password entries to per-user key for {email}', [
                'n'     => $count,
                'email' => $user->getEmail(),
            ]);
        }
    }
}
