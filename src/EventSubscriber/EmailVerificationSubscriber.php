<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EmailVerificationSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_login',
        'app_logout',
        'app_register',
        'app_verify_email',
        'app_resend_verification',
        'app_verify_pending',
        'app_home',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface $router,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User || $user->isEmailVerified()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        // Starts with _ (profiler, wdt, error pages)
        if ($route !== null && str_starts_with($route, '_')) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->router->generate('app_verify_pending')
        ));
    }
}
