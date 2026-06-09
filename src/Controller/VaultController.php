<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class VaultController extends AbstractController
{
    #[Route('/vaults', name: 'app_vaults')]
    public function index(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', ['title' => 'Mes Coffres']);
    }

    #[Route('/passwords', name: 'app_passwords')]
    public function passwords(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', ['title' => 'Mots de passe']);
    }

    #[Route('/shares', name: 'app_shares')]
    public function shares(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', ['title' => 'Partages']);
    }

    #[Route('/alerts', name: 'app_alerts')]
    public function alerts(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', ['title' => 'Alertes sécurité']);
    }
}
