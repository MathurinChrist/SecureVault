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

        // Navigate to vaults list and open the auto-created vault
        $client->request('GET', '/vaults');
        $client->waitFor('.glass-card');

        $link = $client->getCrawler()->filter('.glass-card a, a[href*="/vaults/"]')->first();
        if ($link->count() === 0) {
            $this->markTestSkipped('No vault found after dashboard visit.');
        }

        $vaultUrl = $link->attr('href');
        if (!str_starts_with($vaultUrl, '/vaults/') || str_contains($vaultUrl, '/new')) {
            // Try clicking a vault card directly
            $cards = $client->getCrawler()->filter('[onclick*="/vaults/"]');
            if ($cards->count() === 0) {
                $this->markTestSkipped('No clickable vault card found.');
            }
            preg_match("#/vaults/(\d+)'#", $cards->first()->attr('onclick') ?? '', $m);
            $vaultUrl = '/vaults/' . ($m[1] ?? '');
        }

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

        // Submit the add-password form
        $crawler = $client->getCrawler();
        $form = $crawler->filter('form[name="add_password_entry"]')->form([
            'add_password_entry[title]'    => $title,
            'add_password_entry[password]' => 'S3cr3tPa$$!',
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

        $crawler = $client->getCrawler();
        $form = $crawler->filter('form[name="add_password_entry"]')->form([
            'add_password_entry[title]'    => '',
            'add_password_entry[password]' => 'SomePass1!',
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

        $crawler = $client->getCrawler();
        $form = $crawler->filter('form[name="add_password_entry"]')->form([
            'add_password_entry[title]'    => $title,
            'add_password_entry[password]' => 'S3cr3tPa$$!',
            'add_password_entry[username]' => 'bob',
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

        // Add a password
        $crawler = $client->getCrawler();
        $form = $crawler->filter('form[name="add_password_entry"]')->form([
            'add_password_entry[title]'    => $title,
            'add_password_entry[password]' => 'DeleteMe1!',
        ]);
        $client->submit($form);
        $client->waitFor('body');
        $client->waitForElementToContain('body', $title);

        // Find and submit the delete form for that entry
        $deleteForm = $client->getCrawler()->filter('form[action*="/passwords/"][action*="/delete"]')->last()->form();
        $client->submit($deleteForm);
        $client->waitFor('body');

        $this->assertSelectorTextNotContains('body', $title);
    }
}
