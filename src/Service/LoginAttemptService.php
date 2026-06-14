<?php

namespace App\Service;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;

class LoginAttemptService
{
    private const THRESHOLD       = 5;
    private const WINDOW_MINUTES  = 15;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlertService $alertService,
        private readonly LoginAttemptRepository $loginAttemptRepository,
    ) {}

    public function recordSuccess(User $user, string $ipAddress): void
    {
        $attempt = new LoginAttempt();
        $attempt->setUser($user);
        $attempt->setIpAddress($ipAddress);
        $attempt->setSuccess(true);
        $this->em->persist($attempt);
        $this->em->flush();
    }

    public function recordFailure(User $user, string $ipAddress): void
    {
        $attempt = new LoginAttempt();
        $attempt->setUser($user);
        $attempt->setIpAddress($ipAddress);
        $attempt->setSuccess(false);
        $this->em->persist($attempt);
        $this->em->flush();

        $since       = new \DateTimeImmutable('-' . self::WINDOW_MINUTES . ' minutes');
        $failedCount = $this->loginAttemptRepository->countFailedSince($user, $since);

        // Alert exactly at the threshold to avoid duplicate alerts on each subsequent attempt
        if ($failedCount === self::THRESHOLD) {
            $this->alertService->createAlert(
                $user,
                'Tentatives de connexion suspectes',
                sprintf(
                    '%d tentatives de connexion échouées en %d minutes depuis l\'IP %s.',
                    $failedCount,
                    self::WINDOW_MINUTES,
                    $ipAddress
                ),
                'warning',
                'security'
            );
        }
    }
}
