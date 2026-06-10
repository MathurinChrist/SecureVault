<?php

namespace App\Controller;

use App\Entity\PasswordEntry;
use App\Entity\Vault;
use App\Form\PasswordEntryType;
use App\Repository\PasswordEntryRepository;
use App\Repository\VaultRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
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

        $recentPasswords = $passwordRepository->findRecentByUser($user, 5);

        $vaults = $vaultRepository->findByUser($user);
        
        $passwordForm = $this->createForm(\App\Form\PasswordEntryType::class, new \App\Entity\PasswordEntry(), [
            'vaults' => $vaults,
        ]);
        
        $editForm = $this->createForm(\App\Form\PasswordEntryType::class, new \App\Entity\PasswordEntry(), [
            'vaults' => $vaults,
            'require_password' => false,
        ]);

        return $this->render('dashboard/index.html.twig', [
            'unreadAlertsCount' => $unreadAlertsCount,
            'passwordsCount' => $passwordsCount,
            'vaultsCount' => $vaultsCount,
            'recentPasswords' => $recentPasswords,
            'password_form' => $passwordForm->createView(),
            'edit_form' => $editForm->createView(),
            'open_modal' => false,
        ]);
    }
}
