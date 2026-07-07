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

        // Form login carries the identifier in the POST body; the JSON/API login carries it in
        // the JSON payload. Read both so API brute-force is recorded and subject to lockout.
        $email = trim((string) $request->request->get('email', ''));
        if ($email === '') {
            $payload = json_decode((string) $request->getContent(), true);
            if (is_array($payload)) {
                $email = trim((string) ($payload['email'] ?? ''));
            }
        }

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
