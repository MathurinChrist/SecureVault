<?php

namespace App\Tests\E2E;

class ProfileE2ETest extends AbstractE2ETest
{
    public function testProfilePageLoads(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/profile');
        $client->waitFor('body');

        $this->assertStringContainsString('/profile', $client->getCurrentURL());
        $this->assertSelectorExists('form');
    }

    public function testUpdateProfileFirstAndLastName(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $crawler = $client->request('GET', '/profile');
        $client->waitFor('form');

        $form = $crawler->selectButton('Sauvegarder les modifications')->form([
            'user_profile[firstName]' => 'UpdatedFirst',
            'user_profile[lastName]'  => 'UpdatedLast',
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Should redirect back to /profile
        $this->assertStringContainsString('/profile', $client->getCurrentURL());

        // Name should appear somewhere in the page (navbar, profile heading…)
        $this->assertSelectorTextContains('body', 'UpdatedFirst');
    }

    public function testChangePasswordSucceeds(): void
    {
        $this->skipIfUnavailable();

        $newPassword = 'NewPass456!';
        [$client, $email] = $this->registerAndLogin();

        $client->request('GET', '/profile');
        $client->waitFor('form');
        $client->getCrawler()->filter('#btn-security')->first()->click();
        $client->waitFor('#tab-security:not(.hidden)');

        $form = $client->getCrawler()->filter('#tab-security form')->selectButton('Mettre à jour le mot de passe')->form([
            'change_password[plainPassword][first]'  => $newPassword,
            'change_password[plainPassword][second]' => $newPassword,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Log out and log back in with the new password to verify it worked
        $this->logoutUser($client);
        $this->loginUser($client, $email, $newPassword);

        $this->assertStringContainsString('/dashboard', $client->getCurrentURL());
    }

    public function testProfilePageShowsActivityLog(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/profile');
        $client->waitFor('body');

        $this->assertStringContainsString('/profile', $client->getCurrentURL());

        // Activity section header — hidden behind a tab until clicked
        $client->getCrawler()->filter('#btn-activity')->first()->click();
        $client->waitFor('#tab-activity:not(.hidden)');
        $this->assertSelectorTextContains('body', 'actions');
    }
}
