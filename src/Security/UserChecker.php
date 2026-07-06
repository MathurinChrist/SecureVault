<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use DateTimeImmutable;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Enforced on both the form and the stateless JSON/JWT firewalls.
 *
 * - Deactivated accounts cannot authenticate anywhere.
 * - After too many recent failed attempts the account is temporarily locked, which gives the
 *   app a real brute-force / credential-stuffing brake (there is no login_throttling because
 *   symfony/rate-limiter is not installed, and this covers the API path too).
 */
class UserChecker implements UserCheckerInterface
{
    private const LOCKOUT_THRESHOLD = 10;
    private const WINDOW_MINUTES    = 15;

    public function __construct(
        private readonly LoginAttemptRepository $loginAttemptRepository,
    ) {}

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est désactivé.');
        }

        $since  = new DateTimeImmutable('-' . self::WINDOW_MINUTES . ' minutes');
        $failed = $this->loginAttemptRepository->countFailedSince($user, $since);

        if ($failed >= self::LOCKOUT_THRESHOLD) {
            throw new CustomUserMessageAccountStatusException(
                'Trop de tentatives de connexion. Réessayez dans quelques minutes.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Email-verification gating is handled per-firewall (web: EmailVerificationSubscriber,
        // API: TwoFactorSubscriber), because the web flow intentionally lets an unverified
        // user log in and then confines them to the verification page.
    }
}
