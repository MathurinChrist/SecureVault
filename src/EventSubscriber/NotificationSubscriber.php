<?php

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Event\PasswordUpdatedEvent;
use App\Event\UserRegisteredEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::NAME        => 'onUserRegistered',
            PasswordUpdatedEvent::NAME       => 'onPasswordUpdated',
            LoginSuccessEvent::class         => 'onLoginSuccess',
        ];
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->notificationService->create(
            $event->getUser(),
            'Bienvenue sur SecureVault !',
            'Votre compte a été créé avec succès. Commencez par créer votre premier coffre pour stocker vos mots de passe en toute sécurité.',
            Notification::TYPE_SUCCESS,
        );
    }

    public function onPasswordUpdated(PasswordUpdatedEvent $event): void
    {
        $this->notificationService->create(
            $event->getUser(),
            'Mot de passe modifié',
            'Votre mot de passe a été modifié. Si vous n\'êtes pas à l\'origine de cette action, contactez le support immédiatement.',
            Notification::TYPE_SECURITY,
        );
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof \App\Entity\User) {
            return;
        }

        $ip = $event->getRequest()->getClientIp() ?? '—';

        $this->notificationService->create(
            $user,
            'Nouvelle connexion détectée',
            sprintf('Connexion depuis l\'adresse IP %s.', $ip),
            Notification::TYPE_INFO,
        );
    }
}
