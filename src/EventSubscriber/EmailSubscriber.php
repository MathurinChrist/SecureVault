<?php

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;

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
            $email = (new TemplatedEmail())
                ->from('security@securevault.com')
                ->to($user->getEmail())
                ->subject('Bienvenue sur SecureVault !')
                ->htmlTemplate('emails/welcome.html.twig')
                ->context([
                    'user_email' => $user->getEmail(),
                ]);

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
