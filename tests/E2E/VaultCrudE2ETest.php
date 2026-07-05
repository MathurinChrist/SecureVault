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

        $client->request('GET', '/vaults');
        $client->waitFor('button[onclick="openCreateModal()"]');
        $client->getCrawler()->filter('button[onclick="openCreateModal()"]')->first()->click();
        $client->waitFor('form[action="/vaults/new"]');

        $form = $client->getCrawler()->filter('form[action="/vaults/new"]')->form([
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

        $client->request('GET', '/vaults');
        $client->waitFor('button[onclick="openCreateModal()"]');
        $client->getCrawler()->filter('button[onclick="openCreateModal()"]')->first()->click();
        $client->waitFor('form[action="/vaults/new"]');

        $form = $client->getCrawler()->filter('form[action="/vaults/new"]')->form([
            'vault[name]' => $vaultName,
        ]);
        $client->submit($form);
        $client->followRedirects();
        $client->waitForElementToContain('body', $vaultName);

        // Click the vault card (navigated via onclick, not an <a> link)
        $client->getCrawler()->filter('[onclick*="/vaults/"]')->first()->click();
        $client->waitFor('body');

        $this->assertMatchesRegularExpression('#/vaults/\d+#', $client->getCurrentURL());
    }

    public function testAccessingAnotherUsersVaultReturns403(): void
    {
        $this->skipIfUnavailable();

        // Create user A with a vault
        [$clientA] = $this->registerAndLogin();
        $clientA->request('GET', '/vaults');
        $clientA->waitFor('button[onclick="openCreateModal()"]');
        $clientA->getCrawler()->filter('button[onclick="openCreateModal()"]')->first()->click();
        $clientA->waitFor('form[action="/vaults/new"]');

        $form = $clientA->getCrawler()->filter('form[action="/vaults/new"]')->form([
            'vault[name]' => 'User A Vault',
        ]);
        $clientA->submit($form);
        $clientA->waitFor('body');

        // Creation redirects back to /vaults (list), not the show page — extract the
        // new vault's id from its card's onclick navigation handler.
        preg_match('#/vaults/(\d+)#', $clientA->getCurrentURL(), $m);
        if (empty($m[1])) {
            $clientA->waitFor('[onclick*="/vaults/"]');
            $card = $clientA->getCrawler()->filter('[onclick*="/vaults/"]')->first();
            preg_match("#/vaults/(\d+)'#", $card->attr('onclick') ?? '', $m);
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
        $this->assertSelectorExists('[onclick*="/vaults/"]');
    }
}
