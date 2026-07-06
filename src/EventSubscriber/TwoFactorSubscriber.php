<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\TwoFactorService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class TwoFactorSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_2fa_verify',
        'app_2fa_resend',
        'app_logout',
        'app_login',
        'app_home',
        'app_register',
        'app_verify_email',
        'app_resend_verification',
        'app_verify_pending',
    ];

    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly RouterInterface $router,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => ['onLoginSuccess', -10],
            KernelEvents::REQUEST    => ['onKernelRequest', -1],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        // The stateless JWT API firewall has no session, so the redirect-based 2FA challenge
        // below cannot run there. Rather than issue a single-factor token, refuse to log a
        // 2FA-enabled account in over the API — it must use the web flow.
        if ($event->getFirewallName() === 'api') {
            if ($user->is2faEnabled()) {
                throw new CustomUserMessageAuthenticationException(
                    'Les comptes avec double authentification doivent se connecter via l\'interface web.'
                );
            }
            return;
        }

        if (!$user->is2faEnabled()) {
            return;
        }

        // Enforced for every authenticator (including Google): if the user opted into 2FA,
        // possession of the first factor alone must not grant access.
        $this->twoFactorService->generateAndSendCode($user, $event->getRequest());

        $event->setResponse(new RedirectResponse(
            $this->router->generate('app_2fa_verify')
        ));
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->twoFactorService->isPending($request)) {
            return;
        }

        $route = $request->attributes->get('_route');

        if ($route === null || in_array($route, self::ALLOWED_ROUTES, true) || str_starts_with($route, '_')) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->router->generate('app_2fa_verify')
        ));
    }
}
