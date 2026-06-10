<?php

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::NAME => 'onUserRegistered',
        ];
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $user = $event->getUser();
        $this->logger->info('Attempting to send welcome email to {email}', ['email' => $user->getEmail()]);

        try {
            $email = (new Email())
                ->from('security@securevault.com')
                ->to($user->getEmail())
                ->subject('Bienvenue sur SecureVault !')
                ->html(sprintf(
                    '<h1>Bienvenue %s !</h1><p>Merci d\'avoir rejoint SecureVault. Votre coffre-fort sécurisé est prêt.</p>',
                    $user->getEmail()
                ));

            $this->mailer->send($email);
            $this->logger->info('Welcome email sent to messenger for {email}', ['email' => $user->getEmail()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email to {email}: {error}', [
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
