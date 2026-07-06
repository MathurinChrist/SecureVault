<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordControllerTest extends WebTestCase
{
    private function skipIfDatabaseUnavailable(): void
    {
        try {
            static::getContainer()->get('doctrine.dbal.default_connection')->executeQuery('SELECT 1');
        } catch (\Exception) {
            $this->markTestSkipped('Database not available.');
        }
    }

    private function createUser(string $email = '', string $password = 'Pass123!'): User
    {
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email ?: 'reset_' . uniqid() . '@example.com')
             ->setFirstName('Reset')
             ->setLastName('Test')
             ->setPassword($hasher->hashPassword($user, $password))
             ->setEmailVerified(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    // ── /reset-password (request form) ───────────────────────────────────────

    public function testRequestPageIsPublicAndShowsForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="reset_password_request_form[email]"]');
    }

    public function testRequestWithKnownEmailRedirectsToCheckEmail(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUser();

        $crawler = $client->request('GET', '/reset-password');
        $form    = $crawler->selectButton('Envoyer le lien de réinitialisation')->form([
            'reset_password_request_form[email]' => $user->getEmail(),
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/reset-password/check-email');
    }

    public function testRequestWithUnknownEmailAlsoRedirectsToCheckEmail(): void
    {
        // Security: must not reveal whether an account exists for this email.
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $crawler = $client->request('GET', '/reset-password');
        $form    = $crawler->selectButton('Envoyer le lien de réinitialisation')->form([
            'reset_password_request_form[email]' => 'nobody_' . uniqid() . '@nowhere.invalid',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/reset-password/check-email');
    }

    // ── /reset-password/check-email ──────────────────────────────────────────

    public function testCheckEmailPageIsAccessibleDirectly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password/check-email');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Vérifiez');
    }

    // ── /reset-password/reset/{token} ────────────────────────────────────────

    public function testResetWithInvalidTokenRedirectsToRequestPage(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();

        $client->request('GET', '/reset-password/reset/not-a-real-token');
        $client->request('GET', '/reset-password/reset'); // token stored in session, then consumed here

        $this->assertResponseRedirects('/reset-password');
    }

    public function testResetWithoutTokenInSessionReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password/reset');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFullResetFlowChangesPasswordAndAllowsLogin(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUser(password: 'OldPass123!');

        $helper     = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetToken = $helper->generateResetToken($user);

        // Visit the emailed link: stores the token in session and redirects to the tokenless URL
        $client->request('GET', '/reset-password/reset/' . $resetToken->getToken());
        $this->assertResponseRedirects('/reset-password/reset');
        $crawler = $client->request('GET', '/reset-password/reset');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Réinitialiser le mot de passe')->form([
            'change_password_form[plainPassword][first]'  => 'NewStrongPass456!',
            'change_password_form[plainPassword][second]' => 'NewStrongPass456!',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'réinitialisé');

        // Old password no longer works, new one does
        $client->request('GET', '/login');
        $client->submitForm('Se connecter', [
            'email'    => $user->getEmail(),
            'password' => 'OldPass123!',
        ]);
        $this->assertResponseRedirects('/login');

        $client->request('GET', '/login');
        $client->submitForm('Se connecter', [
            'email'    => $user->getEmail(),
            'password' => 'NewStrongPass456!',
        ]);
        $this->assertResponseRedirects('/dashboard');
    }

    public function testResetTokenCannotBeReusedAfterSuccessfulReset(): void
    {
        $client = static::createClient();
        $this->skipIfDatabaseUnavailable();
        $user = $this->createUser();

        $helper     = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetToken = $helper->generateResetToken($user);
        $token      = $resetToken->getToken();

        $client->request('GET', '/reset-password/reset/' . $token);
        $crawler = $client->request('GET', '/reset-password/reset');
        $form    = $crawler->selectButton('Réinitialiser le mot de passe')->form([
            'change_password_form[plainPassword][first]'  => 'NewStrongPass456!',
            'change_password_form[plainPassword][second]' => 'NewStrongPass456!',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/login');

        // Re-using the same token must fail — it was consumed by the first reset.
        $client->request('GET', '/reset-password/reset/' . $token);
        $client->request('GET', '/reset-password/reset');

        $this->assertResponseRedirects('/reset-password');
    }

    // ── Login page link ───────────────────────────────────────────────────────

    public function testLoginPageLinksToForgotPassword(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $link = $client->getCrawler()->selectLink('Oublié ?')->link();
        $this->assertStringContainsString('/reset-password', $link->getUri());
    }
}
