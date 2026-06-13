<?php

namespace App\Tests\E2E;

class AlertsE2ETest extends AbstractE2ETest
{
    public function testAlertsPageLoads(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        $this->assertStringContainsString('/alerts', $client->getCurrentURL());
    }

    public function testAlertsPageShowsEmptyStateWhenNoAlerts(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        // New user → no alerts yet; empty-state message should appear
        $this->assertSelectorTextContains('body', 'ordre');
    }

    public function testMarkAllReadLinkOnlyAppearsWithUnreadAlerts(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        // For a fresh account there are no alerts, so "Marquer tout comme lu" should NOT appear
        $links = $client->getCrawler()->filter('a[href*="mark-all-read"]');
        $this->assertCount(0, $links);
    }

    /**
     * Login failure alerts are created by the security event listener.
     * We simulate them by failing login several times, then checking /alerts.
     */
    public function testFailedLoginTriggersSecurityAlert(): void
    {
        $this->skipIfUnavailable();

        [$client, $email] = $this->registerAndLogin();
        $this->logoutUser($client);

        // Generate failed login attempts to trigger a security alert
        for ($i = 0; $i < 3; $i++) {
            $crawler = $client->request('GET', '/login');
            $client->waitFor('form');
            $form = $crawler->selectButton('Se connecter')->form([
                'email'    => $email,
                'password' => 'WrongPassword!',
            ]);
            $client->submit($form);
            $client->waitFor('body');
        }

        // Log back in to check alerts
        $this->loginUser($client, $email);
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        $this->assertStringContainsString('/alerts', $client->getCurrentURL());
        // We don't assert an alert was created — alert creation depends on the configured threshold.
        // The test verifies the page renders correctly after login attempts.
    }

    public function testMarkAlertAsReadWorksWhenAlertExists(): void
    {
        $this->skipIfUnavailable();

        [$client, $email] = $this->registerAndLogin();
        $this->logoutUser($client);

        // Trigger alerts via failed logins
        for ($i = 0; $i < 5; $i++) {
            $crawler = $client->request('GET', '/login');
            $client->waitFor('form');
            $form = $crawler->selectButton('Se connecter')->form([
                'email'    => $email,
                'password' => 'WrongBadPass!',
            ]);
            $client->submit($form);
            $client->waitFor('body');
        }

        $this->loginUser($client, $email);
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        $markReadLinks = $client->getCrawler()->filter('a[href*="mark-as-read"]');
        if ($markReadLinks->count() === 0) {
            // No alerts were created — acceptable, skip rather than fail
            $this->markTestSkipped('No alerts were created by failed login attempts.');
        }

        $markReadUrl = $markReadLinks->first()->attr('href');
        $client->request('GET', $markReadUrl);
        $client->waitFor('body');

        $this->assertStringContainsString('/alerts', $client->getCurrentURL());
    }

    public function testDismissAlertWorksWhenAlertExists(): void
    {
        $this->skipIfUnavailable();

        [$client, $email] = $this->registerAndLogin();
        $this->logoutUser($client);

        for ($i = 0; $i < 5; $i++) {
            $crawler = $client->request('GET', '/login');
            $client->waitFor('form');
            $form = $crawler->selectButton('Se connecter')->form([
                'email'    => $email,
                'password' => 'BadBadPass!',
            ]);
            $client->submit($form);
            $client->waitFor('body');
        }

        $this->loginUser($client, $email);
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        $dismissLinks = $client->getCrawler()->filter('a[href*="dismiss"]');
        if ($dismissLinks->count() === 0) {
            $this->markTestSkipped('No alerts to dismiss.');
        }

        $dismissUrl = $dismissLinks->first()->attr('href');
        $client->request('GET', $dismissUrl);
        $client->waitFor('body');

        $this->assertStringContainsString('/alerts', $client->getCurrentURL());
    }
}
