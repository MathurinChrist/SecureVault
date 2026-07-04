<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\ActivityLogService;
use App\Service\LoginAttemptService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $ip = $event->getRequest()->getClientIp() ?? 'unknown';

        $this->loginAttemptService->recordSuccess($user, $ip);
        $this->activityLogService->log($user, 'Connexion réussie depuis ' . $ip);
    }
}
