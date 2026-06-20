<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'app_google_connect')]
    public function connect(ClientRegistry $clientRegistry, Request $request): Response
    {
        // store origin so the authenticator can tailor the flash message
        $request->getSession()->set('_google_source', $request->query->get('source', 'login'));

        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/callback', name: 'app_google_callback')]
    public function callback(): Response
    {
        // handled entirely by GoogleAuthenticator
        return $this->redirectToRoute('app_dashboard');
    }
}
