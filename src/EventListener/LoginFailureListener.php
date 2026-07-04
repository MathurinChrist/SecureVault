<?php

namespace App\EventListener;

use App\Repository\UserRepository;
use App\Service\LoginAttemptService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[AsEventListener(event: LoginFailureEvent::class)]
class LoginFailureListener
{
    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
        private readonly UserRepository $userRepository,
    ) {}

    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $ip      = $request->getClientIp() ?? 'unknown';
        $email   = trim((string) $request->request->get('email', ''));

        if ($email === '') {
            return;
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            return;
        }

        $this->loginAttemptService->recordFailure($user, $ip);
    }
}
