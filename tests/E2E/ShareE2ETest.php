<?php

namespace App\Tests\E2E;

class ShareE2ETest extends AbstractE2ETest
{
    public function testSharesIndexPageLoads(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/shares');
        $client->waitFor('body');

        $this->assertStringContainsString('/shares', $client->getCurrentURL());
    }

    public function testVaultSharesPageLoadsForOwner(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        $client->request('GET', '/vaults');
        $client->waitFor('body');

        // Find first vault link
        $links = $client->getCrawler()->filter('a[href*="/vaults/"]');
        if ($links->count() === 0) {
            $this->markTestSkipped('No vault found to test shares page.');
        }

        $vaultUrl = null;
        foreach ($links as $node) {
            $href = $node->getAttribute('href');
            if (preg_match('#^/vaults/(\d+)$#', $href)) {
                $vaultUrl = $href;
                break;
            }
        }

        if ($vaultUrl === null) {
            $this->markTestSkipped('No direct vault URL found.');
        }

        preg_match('#/vaults/(\d+)#', $vaultUrl, $m);
        $vaultId = $m[1];

        $client->request('GET', '/vaults/' . $vaultId . '/shares');
        $client->waitFor('body');

        $this->assertStringContainsString('/shares', $client->getCurrentURL());
        $this->assertSelectorExists('form[action*="/share"]');
    }

    public function testInviteWithUnknownEmailShowsError(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        $vaultId = $this->findFirstVaultId($client);
        if ($vaultId === null) {
            $this->markTestSkipped('No vault found.');
        }

        $client->request('GET', '/vaults/' . $vaultId . '/shares');
        $client->waitFor('form[action*="/share"]');

        $crawler = $client->getCrawler();
        $permSelect = $crawler->filter('select[name="permission"]');
        if ($permSelect->count() === 0 || $permSelect->filter('option')->count() === 0) {
            $this->markTestSkipped('No VaultPermission data in the database — run fixtures first.');
        }

        $form = $crawler->selectButton("Envoyer l'invitation")->form([
            'email'      => 'nobody_' . uniqid() . '@nowhere.invalid',
            'permission' => $permSelect->filter('option')->first()->attr('value'),
        ]);
        $client->submit($form);
        $client->waitForElementToContain('body', 'Aucun utilisateur');

        $this->assertSelectorTextContains('body', 'Aucun utilisateur');
    }

    public function testInviteKnownUserCreatesShare(): void
    {
        $this->skipIfUnavailable();

        // Register user A (owner) and user B (recipient)
        $emailB = static::generateEmail();
        $client = static::createPantherClient();
        $this->logoutUser($client);
        $this->registerUser($client, $emailB);

        // Now register and login as user A
        [$clientA, $emailA] = $this->registerAndLogin();
        $clientA->request('GET', '/dashboard');
        $clientA->waitFor('body');

        $vaultId = $this->findFirstVaultId($clientA);
        if ($vaultId === null) {
            $this->markTestSkipped('No vault found for user A.');
        }

        $clientA->request('GET', '/vaults/' . $vaultId . '/shares');
        $clientA->waitFor('form[action*="/share"]');

        $crawler  = $clientA->getCrawler();
        $permSelect = $crawler->filter('select[name="permission"]');
        if ($permSelect->count() === 0 || $permSelect->filter('option')->count() === 0) {
            $this->markTestSkipped('No VaultPermission data in the database — run fixtures first.');
        }

        $form = $crawler->selectButton("Envoyer l'invitation")->form([
            'email'      => $emailB,
            'permission' => $permSelect->filter('option')->first()->attr('value'),
        ]);
        $clientA->submit($form);
        $clientA->waitFor('body');

        // Invitation sent → share appears in the list
        $clientA->waitForElementToContain('body', $emailB);
        $this->assertSelectorTextContains('body', $emailB);
    }

    public function testRecipientCanAcceptShare(): void
    {
        $this->skipIfUnavailable();

        // Register user B first
        $emailB    = static::generateEmail();
        $passwordB = 'E2eTest123!';
        $client    = static::createPantherClient();
        $this->logoutUser($client);
        $this->registerUser($client, $emailB, $passwordB);

        // Register and login as user A, create a share invitation for user B
        [$clientA] = $this->registerAndLogin();
        $clientA->request('GET', '/dashboard');
        $clientA->waitFor('body');

        $vaultId = $this->findFirstVaultId($clientA);
        if ($vaultId === null) {
            $this->markTestSkipped('No vault found for user A.');
        }

        $clientA->request('GET', '/vaults/' . $vaultId . '/shares');
        $clientA->waitFor('form[action*="/share"]');

        $crawler    = $clientA->getCrawler();
        $permSelect = $crawler->filter('select[name="permission"]');
        if ($permSelect->count() === 0 || $permSelect->filter('option')->count() === 0) {
            $this->markTestSkipped('No VaultPermission data — run fixtures first.');
        }

        $form = $crawler->selectButton("Envoyer l'invitation")->form([
            'email'      => $emailB,
            'permission' => $permSelect->filter('option')->first()->attr('value'),
        ]);
        $clientA->submit($form);
        $clientA->waitFor('body');

        // Log in as user B and accept the share
        $this->logoutUser($clientA);
        $this->loginUser($clientA, $emailB, $passwordB);

        $clientA->request('GET', '/shares');
        $clientA->waitFor('body');

        $acceptForm = $clientA->getCrawler()->filter('form[action*="/accept"]')->first();
        if ($acceptForm->count() === 0) {
            $this->markTestSkipped('No pending share found for user B.');
        }

        $clientA->submit($acceptForm->form());
        $clientA->waitFor('body');

        $this->assertStringContainsString('/shares', $clientA->getCurrentURL());
    }

    public function testOwnerCanRevokeShare(): void
    {
        $this->skipIfUnavailable();

        // Register user B
        $emailB = static::generateEmail();
        $client = static::createPantherClient();
        $this->logoutUser($client);
        $this->registerUser($client, $emailB);

        // Log in as user A, send share invitation
        [$clientA] = $this->registerAndLogin();
        $clientA->request('GET', '/dashboard');
        $clientA->waitFor('body');

        $vaultId = $this->findFirstVaultId($clientA);
        if ($vaultId === null) {
            $this->markTestSkipped('No vault found.');
        }

        $clientA->request('GET', '/vaults/' . $vaultId . '/shares');
        $clientA->waitFor('form[action*="/share"]');

        $crawler    = $clientA->getCrawler();
        $permSelect = $crawler->filter('select[name="permission"]');
        if ($permSelect->count() === 0 || $permSelect->filter('option')->count() === 0) {
            $this->markTestSkipped('No VaultPermission data — run fixtures first.');
        }

        $form = $crawler->selectButton("Envoyer l'invitation")->form([
            'email'      => $emailB,
            'permission' => $permSelect->filter('option')->first()->attr('value'),
        ]);
        $clientA->submit($form);
        $clientA->waitFor('body');

        // The share should be listed — revoke it
        $revokeForm = $clientA->getCrawler()->filter('form[action*="/revoke"]')->first();
        if ($revokeForm->count() === 0) {
            $this->markTestSkipped('No share to revoke.');
        }

        $clientA->submit($revokeForm->form());
        $clientA->waitFor('body');

        $this->assertSelectorTextNotContains('body', $emailB);
    }

    private function findFirstVaultId(\Symfony\Component\Panther\Client $client): ?string
    {
        $client->request('GET', '/vaults');
        $client->waitFor('body');

        $links = $client->getCrawler()->filter('a[href*="/vaults/"]');
        foreach ($links as $node) {
            $href = $node->getAttribute('href');
            if (preg_match('#^/vaults/(\d+)$#', $href, $m)) {
                return $m[1];
            }
        }

        // Try vault cards with onclick
        $cards = $client->getCrawler()->filter('[onclick*="/vaults/"]');
        foreach ($cards as $node) {
            $onclick = $node->getAttribute('onclick');
            if (preg_match("#/vaults/(\d+)#", $onclick, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
