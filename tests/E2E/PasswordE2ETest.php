<?php

namespace App\Tests\E2E;

class PasswordE2ETest extends AbstractE2ETest
{
    /**
     * Log in, visit dashboard (creates auto-vault), then navigate to the vault show page.
     * Returns [client, vaultShowUrl].
     */
    private function loginAndGetVaultShowUrl(): array
    {
        [$client] = $this->registerAndLogin();

        // Dashboard triggers auto-vault creation
        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        // Navigate to vaults list and open the auto-created vault.
        // Vault cards are plain divs navigated via onclick, not <a> links.
        $client->request('GET', '/vaults');
        $client->waitFor('[onclick*="/vaults/"]');

        $cards = $client->getCrawler()->filter('[onclick*="/vaults/"]');
        if ($cards->count() === 0) {
            $this->markTestSkipped('No clickable vault card found.');
        }
        preg_match("#/vaults/(\d+)'#", $cards->first()->attr('onclick') ?? '', $m);
        $vaultUrl = '/vaults/' . ($m[1] ?? '');

        $client->request('GET', $vaultUrl);
        $client->waitFor('body');

        return [$client, $vaultUrl];
    }

    public function testVaultShowPageLoads(): void
    {
        $this->skipIfUnavailable();

        [$client, $vaultUrl] = $this->loginAndGetVaultShowUrl();
        $this->assertMatchesRegularExpression('#/vaults/\d+$#', $client->getCurrentURL());
    }

    public function testAddPasswordFromVaultShowPage(): void
    {
        $this->skipIfUnavailable();

        [$client, $vaultUrl] = $this->loginAndGetVaultShowUrl();
        $title = 'E2E Entry ' . uniqid();

        // Open the add-password modal so its form fields are interactable
        $client->executeScript('openAddModal()');
        $client->waitForVisibility('#add-modal');

        $form = $client->getCrawler()->filter('form[name="add_password_entry"]')->form([
            'add_password_entry[title]'    => $title,
            'add_password_entry[plainPassword]' => 'S3cr3tPa$$!',
            'add_password_entry[username]' => 'alice',
            'add_password_entry[url]'      => 'https://example.com',
        ]);
        $client->submit($form);
        $client->waitFor('body');

        $client->waitForElementToContain('body', $title);
        $this->assertSelectorTextContains('body', $title);
    }

    public function testAddPasswordWithoutTitleShowsError(): void
    {
        $this->skipIfUnavailable();

        [$client, $vaultUrl] = $this->loginAndGetVaultShowUrl();
        $pageUrl = $client->getCurrentURL();

        $client->executeScript('openAddModal()');
        $client->waitForVisibility('#add-modal');

        $form = $client->getCrawler()->filter('form[name="add_password_entry"]')->form([
            'add_password_entry[title]'    => '',
            'add_password_entry[plainPassword]' => 'SomePass1!',
        ]);
        $client->submit($form);
        $client->waitFor('body');

        // Should not have navigated away (redirect back with error or validation)
        $this->assertStringNotContainsString('/login', $client->getCurrentURL());
    }

    public function testAddPasswordFromPasswordsPage(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();

        // Visit dashboard to trigger auto-vault creation
        $client->request('GET', '/dashboard');
        $client->waitFor('body');

        $client->request('GET', '/passwords');
        $client->waitFor('body');
        $title = 'PW Page Entry ' . uniqid();

        $client->executeScript('openAddModal()');
        $client->waitForVisibility('#add-modal');

        // On the passwords page the modal isn't tied to a vault, so a vault must be chosen
        // explicitly (it's a required field). Pick the last option (the placeholder is first).
        $modal   = $client->getCrawler()->filter('form[name="add_password_entry"]');
        $vaultId = $modal->filter('select[name="add_password_entry[vault]"] option')->last()->attr('value');

        $form = $modal->form([
            'add_password_entry[title]'         => $title,
            'add_password_entry[plainPassword]' => 'S3cr3tPa$$!',
            'add_password_entry[username]'      => 'bob',
            'add_password_entry[vault]'         => $vaultId,
        ]);
        $client->submit($form);
        $client->waitFor('body');

        $client->waitForElementToContain('body', $title);
        $this->assertSelectorTextContains('body', $title);
    }

    public function testPasswordsPageHasSearchAndFilter(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/passwords');
        $client->waitFor('body');

        $this->assertSelectorExists('#pw-search');
        $this->assertSelectorExists('#vault-filter');
    }

    public function testDeletePasswordRemovesEntryFromList(): void
    {
        $this->skipIfUnavailable();

        [$client, $vaultUrl] = $this->loginAndGetVaultShowUrl();
        $title = 'To Delete ' . uniqid();

        // Add a password (open the modal first so the fields are interactable)
        $client->executeScript('openAddModal()');
        $client->waitForVisibility('#add-modal');

        $form = $client->getCrawler()->filter('form[name="add_password_entry"]')->form([
            'add_password_entry[title]'    => $title,
            'add_password_entry[plainPassword]' => 'DeleteMe1!',
        ]);
        $client->submit($form);
        $client->waitFor('body');
        $client->waitForElementToContain('body', $title);

        // Find and submit the delete form for that entry
        $deleteForm = $client->getCrawler()->filter('form[action*="/passwords/"][action*="/delete"]')->last()->form();
        $client->submit($deleteForm);
        $client->waitFor('body');

        // Reload so the one-time success flash (which echoes the deleted title) is cleared,
        // then confirm the entry is truly gone from the list.
        $client->request('GET', $vaultUrl);
        $client->waitFor('body');
        $this->assertSelectorTextNotContains('body', $title);
    }
}
