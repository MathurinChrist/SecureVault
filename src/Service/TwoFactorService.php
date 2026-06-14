<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class TwoFactorService
{
    private const SESSION_CODE       = '2fa_code';
    private const SESSION_EXPIRES_AT = '2fa_expires_at';
    private const SESSION_PENDING    = '2fa_pending';
    private const TTL_SECONDS        = 600; // 10 minutes

    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function generateAndSendCode(User $user, Request $request): void
    {
        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $session = $request->getSession();

        $session->set(self::SESSION_CODE, $code);
        $session->set(self::SESSION_EXPIRES_AT, time() + self::TTL_SECONDS);
        $session->set(self::SESSION_PENDING, true);

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@securevault.local', 'SecureVault'))
            ->to($user->getEmail())
            ->subject('Votre code de connexion — SecureVault')
            ->htmlTemplate('emails/2fa_code.html.twig')
            ->context(['code' => $code, 'user' => $user]);

        $this->mailer->send($email);
    }

    public function isPending(Request $request): bool
    {
        return (bool) $request->getSession()->get(self::SESSION_PENDING, false);
    }

    public function verifyCode(Request $request, string $submitted): bool
    {
        $session   = $request->getSession();
        $stored    = $session->get(self::SESSION_CODE);
        $expiresAt = (int) $session->get(self::SESSION_EXPIRES_AT, 0);

        if (!$stored || time() > $expiresAt) {
            return false;
        }

        return hash_equals($stored, $submitted);
    }

    public function clearPending(Request $request): void
    {
        $session = $request->getSession();
        $session->remove(self::SESSION_CODE);
        $session->remove(self::SESSION_EXPIRES_AT);
        $session->remove(self::SESSION_PENDING);
    }
}
