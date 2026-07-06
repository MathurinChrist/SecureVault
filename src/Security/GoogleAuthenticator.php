<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry         $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface        $router,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_google_callback';
    }

    public function authenticate(Request $request): Passport
    {
        $client      = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        $session     = $request->getSession();

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $session) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email    = $googleUser->getEmail();
                $googleId = $googleUser->getId();

                // Only trust the email if Google asserts it is verified. Without this check an
                // attacker who controls an OAuth identity with an arbitrary/unverified email
                // claim could match a victim's address.
                $claims = $googleUser->toArray();
                if (($claims['email_verified'] ?? false) !== true) {
                    throw new CustomUserMessageAuthenticationException(
                        'Votre adresse Google n\'est pas vérifiée. Connexion impossible.'
                    );
                }

                // already linked to this Google account → silent login
                $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => $googleId]);
                if ($user) {
                    $session->set('_google_login', 'returning');
                    return $user;
                }

                // An account already exists for this email but is NOT linked to this Google
                // identity. Do not silently link and log in — that would let anyone holding a
                // Google token for the address take over a password-protected account. Require
                // the user to sign in with their password and link Google explicitly.
                $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing) {
                    throw new CustomUserMessageAuthenticationException(
                        'Un compte existe déjà avec cet e-mail. Connectez-vous avec votre mot de passe, '
                        . 'puis liez votre compte Google depuis votre profil.'
                    );
                }

                // new user → create account
                $nameParts = explode(' ', $googleUser->getName() ?? $email, 2);
                $user = (new User())
                    ->setEmail($email)
                    ->setGoogleId($googleId)
                    ->setFirstName($nameParts[0])
                    ->setLastName($nameParts[1] ?? '')
                    // Random unusable credential: not a valid hash, so form login can never
                    // match it — these accounts must authenticate through Google.
                    ->setPassword(bin2hex(random_bytes(32)))
                    ->setEmailVerified(true)
                    ->setIsActive(true);

                $this->em->persist($user);
                $this->em->flush();
                $session->set('_google_login', 'created');
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $_token, string $_firewallName): ?Response
    {
        $session  = $request->getSession();
        $scenario = $session->get('_google_login');
        $source   = $session->get('_google_source', 'login');
        $session->remove('_google_login');
        $session->remove('_google_source');

        if ($session instanceof FlashBagAwareSessionInterface) {
            $flash = $session->getFlashBag();

            if ($scenario === 'created') {
                $flash->add('success', 'Compte créé avec succès via Google. Bienvenue sur SecureVault !');
            } elseif ($scenario === 'linked') {
                $flash->add('success', 'Votre compte Google a été lié à votre compte existant. Vous êtes maintenant connecté.');
            } elseif ($scenario === 'returning' && $source === 'register') {
                // user clicked "S'inscrire" but already has an account → inform them
                $flash->add('success', 'Vous avez déjà un compte SecureVault. Vous avez été connecté automatiquement.');
            }
        }

        return new RedirectResponse($this->router->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(
                'error',
                'Connexion Google échouée : ' . strtr($exception->getMessageKey(), $exception->getMessageData())
            );
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $_request, ?AuthenticationException $_authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
