<?php

namespace App\Tests\E2E;

/**
 * Full vault CRUD E2E test suite.
 * Replaces the old VaultCrudE2ETest that incorrectly used getContainer() to create users.
 */
class VaultCrudE2ETest extends AbstractE2ETest
{
    public function testVaultsPageLoadsAfterLogin(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/vaults');
        $client->waitFor('body');

        $this->assertStringNotContainsString('/login', $client->getCurrentURL());
        $this->assertStringContainsString('/vaults', $client->getCurrentURL());
    }

    public function testCreateVaultFormExists(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/vaults');
        $client->waitFor('body');

        $this->assertSelectorExists('form[action="/vaults/new"]');
        $this->assertSelectorExists('input[name="vault[name]"]');
    }

    public function testCreateVaultAndSeeItInList(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $vaultName = 'E2E Vault ' . uniqid();

        $crawler = $client->request('GET', '/vaults');
        $client->waitFor('form[action="/vaults/new"]');

        $form = $crawler->filter('form[action="/vaults/new"]')->form([
            'vault[name]'        => $vaultName,
            'vault[description]' => 'Created by E2E test',
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Follows redirect back to /vaults
        $client->waitForElementToContain('body', $vaultName);
        $this->assertSelectorTextContains('body', $vaultName);
    }

    public function testClickingVaultCardNavigatesToShowPage(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $vaultName = 'Clickable ' . uniqid();

        $crawler = $client->request('GET', '/vaults');
        $client->waitFor('form[action="/vaults/new"]');

        $form = $crawler->filter('form[action="/vaults/new"]')->form([
            'vault[name]' => $vaultName,
        ]);
        $client->submit($form);
        $client->followRedirects();
        $client->waitForElementToContain('body', $vaultName);

        // Click the vault card
        $client->clickLink($vaultName);
        $client->waitFor('body');

        $this->assertMatchesRegularExpression('#/vaults/\d+#', $client->getCurrentURL());
    }

    public function testAccessingAnotherUsersVaultReturns403(): void
    {
        $this->skipIfUnavailable();

        // Create user A with a vault
        [$clientA] = $this->registerAndLogin();
        $crawler = $clientA->request('GET', '/vaults');
        $clientA->waitFor('form[action="/vaults/new"]');

        $form = $crawler->filter('form[action="/vaults/new"]')->form([
            'vault[name]' => 'User A Vault',
        ]);
        $clientA->submit($form);
        $clientA->waitFor('body');

        // Extract vault id from the page
        preg_match('#/vaults/(\d+)#', $clientA->getCurrentURL(), $m);
        if (empty($m[1])) {
            // Navigate to vault show to get the ID
            $clientA->clickLink('User A Vault');
            $clientA->waitFor('body');
            preg_match('#/vaults/(\d+)#', $clientA->getCurrentURL(), $m);
        }
        $vaultId = $m[1] ?? null;
        $this->assertNotNull($vaultId, 'Could not determine vault ID');

        // Log in as user B and try to access user A's vault
        [$clientB] = $this->registerAndLogin();
        $clientB->request('GET', '/vaults/' . $vaultId);
        $clientB->waitFor('body');

        $this->assertSelectorTextContains('body', '403');
    }

    public function testDashboardShowsAutoCreatedVaultOnFirstLogin(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();

        // Dashboard triggers auto-vault creation on first visit
        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        $this->assertStringContainsString('/dashboard', $client->getCurrentURL());

        // Check that the vault list is not empty (auto-vault should exist)
        $client->request('GET', '/vaults');
        $client->waitFor('body');
        $this->assertSelectorExists('.glass-card');
    }
}
