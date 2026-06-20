<?php

namespace App\Controller;

use App\Entity\PasswordEntry;
use App\Entity\Vault;
use App\Form\PasswordEntryType;
use App\Repository\AlertRepository;
use App\Repository\PasswordEntryRepository;
use App\Repository\VaultRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'VAULT_ENCRYPTION_KEY')]
        private readonly string $encryptionKey,
    ) {}

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        AlertRepository $alertRepository,
        PasswordEntryRepository $passwordEntryRepository,
        VaultRepository $vaultRepository,
        EntityManagerInterface $entityManager,
        EncryptionService $encryptionService,
        FormFactoryInterface $formFactory,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $unreadAlerts      = $alertRepository->findUnreadByUser($user);
        $unreadAlertsCount = count($unreadAlerts);
        $passwordsCount    = $passwordEntryRepository->countByUser($user);
        $vaultsCount       = count($vaultRepository->findByUser($user));
        $oldPasswordsCount = $passwordEntryRepository->countOldByUser($user, 180);

        if ($passwordsCount === 0 && $vaultsCount === 0) {
            $vault = new Vault();
            $vault->setName('Coffre Personnel');
            $vault->setUser($user);
            $entityManager->persist($vault);

            $key = hash('sha256', $this->encryptionKey, true);

            $services = [
                ['title' => 'Google',  'username' => 'john.doe@gmail.com'],
                ['title' => 'GitHub',  'username' => 'jdoe_dev'],
                ['title' => 'Netflix', 'username' => 'family.doe'],
            ];

            foreach ($services as $s) {
                $p = new PasswordEntry();
                $p->setTitle($s['title']);
                $p->setUsername($s['username']);
                $p->setEncryptedPassword($encryptionService->encrypt('demo_password', $key));
                $p->setVault($vault);
                $p->setUser($user);
                $entityManager->persist($p);
            }

            $entityManager->flush();
            $passwordsCount = count($services);
            $vaultsCount    = 1;
        }

        $unreadAlerts    = $alertRepository->findUnreadByUser($user);
        $score = 100;
        $score -= min($unreadAlertsCount * 10, 50);
        if ($passwordsCount > 0) {
            $score -= (int)(($oldPasswordsCount / $passwordsCount) * 30);
        }
        $score = max($score, 0);

        $recentAlerts    = array_slice($unreadAlerts, 0, 3);
        $recentPasswords = $passwordEntryRepository->findRecentByUser($user, 6);
        $vaults          = $vaultRepository->findByUser($user);

        $passwordForm = $formFactory->createNamed('add_password_entry', PasswordEntryType::class, new PasswordEntry(), [
            'vaults'           => $vaults,
            'action'           => $this->generateUrl('app_password_new'),
            'method'           => 'POST',
        ]);

        $editForm = $formFactory->createNamed('edit_password_entry', PasswordEntryType::class, new PasswordEntry(), [
            'vaults'           => $vaults,
            'require_password' => false,
        ]);

        return $this->render('dashboard/index.html.twig', [
            'unreadAlertsCount' => $unreadAlertsCount,
            'recentAlerts'      => $recentAlerts,
            'passwordsCount'    => $passwordsCount,
            'vaultsCount'       => $vaultsCount,
            'oldPasswordsCount' => $oldPasswordsCount,
            'recentPasswords'   => $recentPasswords,
            'score'             => $score,
            'password_form'     => $passwordForm->createView(),
            'edit_form'         => $editForm->createView(),
            'open_modal'        => false,
        ]);
    }
}
