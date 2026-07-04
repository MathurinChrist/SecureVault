<?php

namespace App\EventSubscriber;

use App\Entity\UserSession;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private \App\Service\AlertService $alertService,
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            \App\Event\PasswordUpdatedEvent::NAME => 'onPasswordUpdated',
        ];
    }

    public function onPasswordUpdated(\App\Event\PasswordUpdatedEvent $event): void
    {
        $this->alertService->createAlert(
            $event->getUser(),
            'Mot de passe modifié',
            'Votre mot de passe a été mis à jour avec succès.',
            'warning',
            'security'
        );
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $this->logger->info('Login SUCCESS for user: {email}', ['email' => $user->getUserIdentifier()]);
        $request = $event->getRequest();

        if (!$user instanceof \App\Entity\User) {
            return;
        }

        $session = new UserSession();
        $session->setUser($user);
        $session->setIpAddress($request->getClientIp() ?? '127.0.0.1');
        $session->setUserAgent($request->headers->get('User-Agent'));
        $session->setLocation('Paris, France');

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $userAgent = $request->headers->get('User-Agent');

        $this->alertService->createAlert(
            $user,
            'Nouvelle connexion détectée',
            sprintf(
                'Nouvelle connexion depuis l\'IP %s. User-Agent : %s',
                $session->getIpAddress(),
                $userAgent
            ),
            'info',
            'login'
        );
    }
}
