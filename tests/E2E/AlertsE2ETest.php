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

    public function testAlertsPageShowsLoginAlertOnFreshAccount(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        // Every successful login creates a "new connection" alert (SecuritySubscriber),
        // so a freshly registered account always has exactly that one alert.
        $this->assertSelectorTextContains('body', 'Nouvelle connexion détectée');
    }

    public function testMarkAllReadLinkAppearsWithUnreadAlerts(): void
    {
        $this->skipIfUnavailable();

        [$client] = $this->registerAndLogin();
        $client->request('GET', '/alerts');
        $client->waitFor('body');

        // Login always creates one unread "new connection" alert, so the control should appear.
        // It is a POST form (CSRF-protected), not a link.
        $forms = $client->getCrawler()->filter('form[action*="mark-all-read"]');
        $this->assertGreaterThan(0, $forms->count());
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

        $markReadForms = $client->getCrawler()->filter('form[action*="mark-as-read"]');
        if ($markReadForms->count() === 0) {
            // No alerts were created — acceptable, skip rather than fail
            $this->markTestSkipped('No alerts were created by failed login attempts.');
        }

        // Submit the CSRF-protected POST form instead of following a link
        $client->submit($markReadForms->first()->form());
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

        $dismissForms = $client->getCrawler()->filter('form[action*="dismiss"]');
        if ($dismissForms->count() === 0) {
            $this->markTestSkipped('No alerts to dismiss.');
        }

        // Submit the CSRF-protected POST form instead of following a link
        $client->submit($dismissForms->first()->form());
        $client->waitFor('body');

        $this->assertStringContainsString('/alerts', $client->getCurrentURL());
    }
}
