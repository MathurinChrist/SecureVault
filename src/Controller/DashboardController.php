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
    public function __construct(
        #[Autowire('%env(VAULT_ENCRYPTION_KEY)%')] private readonly string $encryptionKey
    ) {}

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        VaultRepository $vaultRepository,
        PasswordEntryRepository $passwordEntryRepository,
        EncryptionService $encryptionService,
        FormFactoryInterface $formFactory,
    ): Response {
        $user = $this->getUser();

        $vaults = $vaultRepository->findBy(['owner' => $user, 'archived' => false]);

        $newVault = null;
        if (empty($vaults)) {
            $newVault = (new Vault())->setName('Personnel')->setOwner($user);
            $em->persist($newVault);
            $vaults = [$newVault];
        }

        // Formulaire d'ajout
        $addEntry = new PasswordEntry();
        $addForm  = $this->createForm(PasswordEntryType::class, $addEntry, [
            'vaults' => $vaults,
        ]);
        $addForm->handleRequest($request);

        if ($addForm->isSubmitted() && $addForm->isValid()) {
            $key = hash('sha256', $this->encryptionKey, true);
            $addEntry->setEncryptedPassword(
                $encryptionService->encrypt($addForm->get('plainPassword')->getData(), $key)
            );
            $em->persist($addEntry);
            $em->flush(); // flush inclut le vault par défaut si il vient d'être créé
            $newVault = null;

            $this->addFlash('success', 'Mot de passe ajouté avec succès.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Sur GET (ou POST invalide sans entrée sauvegardée) : persiste le vault par défaut seul
        if ($newVault !== null) {
            $em->flush();
        }

        // Formulaire d'édition (nom distinct pour éviter les conflits d'ID HTML)
        $editForm = $formFactory->createNamed('edit_password_entry', PasswordEntryType::class, new PasswordEntry(), [
            'vaults'           => $vaults,
            'require_password' => false,
        ]);

        $entries = $passwordEntryRepository->findByUser($user);

        return $this->render('dashboard/index.html.twig', [
            'password_form' => $addForm,
            'edit_form'     => $editForm,
            'open_modal'    => $addForm->isSubmitted() && !$addForm->isValid(),
            'entries'       => $entries,
            'total_vaults'  => \count($vaults),
        ]);
    }
}
