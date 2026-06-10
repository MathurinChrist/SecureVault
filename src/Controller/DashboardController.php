<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        \App\Repository\AlertRepository $alertRepository,
        \App\Repository\PasswordRepository $passwordRepository,
        \App\Repository\VaultRepository $vaultRepository,
        \Doctrine\ORM\EntityManagerInterface $entityManager
    ): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $unreadAlertsCount = count($alertRepository->findUnreadByUser($user));
        $passwordsCount = $passwordRepository->countByUser($user);
        $vaultsCount = count($vaultRepository->findByUser($user));
        
        // Demo data generation
        if ($passwordsCount === 0 && $vaultsCount === 0) {
            $vault = new \App\Entity\Vault();
            $vault->setName('Coffre Personnel');
            $vault->setUser($user);
            $entityManager->persist($vault);
            
            $services = [
                ['name' => 'Google', 'user' => 'john.doe@gmail.com'],
                ['name' => 'GitHub', 'user' => 'jdoe_dev'],
                ['name' => 'Netflix', 'user' => 'family.doe'],
            ];
            
            foreach ($services as $s) {
                $p = new \App\Entity\Password();
                $p->setServiceName($s['name']);
                $p->setUsername($s['user']);
                $p->setEncryptedPassword('encrypted_placeholder');
                $p->setVault($vault);
                $p->setUser($user);
                $entityManager->persist($p);
            }
            
            $entityManager->flush();
            $passwordsCount = count($services);
            $vaultsCount = 1;
        }

        $recentPasswords = $passwordRepository->findRecentByUser($user, 5);

        return $this->render('dashboard/index.html.twig', [
            'unreadAlertsCount' => $unreadAlertsCount,
            'passwordsCount' => $passwordsCount,
            'vaultsCount' => $vaultsCount,
            'recentPasswords' => $recentPasswords,
        ]);
    }
}
