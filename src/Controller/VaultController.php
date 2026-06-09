<?php

namespace App\Controller;

use App\Entity\PasswordEntry;
use App\Entity\Vault;
use App\Form\PasswordEntryType;
use App\Form\VaultType;
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
class VaultController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(VAULT_ENCRYPTION_KEY)%')] private readonly string $encryptionKey
    ) {}

    #[Route('/vaults', name: 'app_vaults', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        VaultRepository $vaultRepository,
        EntityManagerInterface $em,
    ): Response {
        $user   = $this->getUser();
        $vaults = $vaultRepository->findBy(['owner' => $user], ['archived' => 'ASC', 'createdAt' => 'DESC']);

        $newVault   = new Vault();
        $createForm = $this->createForm(VaultType::class, $newVault);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $newVault->setOwner($user);
            $em->persist($newVault);
            $em->flush();
            $this->addFlash('success', 'Coffre "' . $newVault->getName() . '" créé avec succès.');
            return $this->redirectToRoute('app_vaults');
        }

        return $this->render('vault/index.html.twig', [
            'vaults'      => $vaults,
            'create_form' => $createForm,
            'open_modal'  => $createForm->isSubmitted() && !$createForm->isValid(),
        ]);
    }

    #[Route('/vaults/{id}/edit', name: 'app_vault_edit', methods: ['POST'])]
    public function edit(
        Vault $vault,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($vault->getOwner()->getUserIdentifier() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('vault_edit_' . $vault->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('error', 'Le nom du coffre est obligatoire.');
            return $this->redirectToRoute('app_vaults');
        }

        $vault->setName($name);
        $vault->setDescription($request->request->get('description') ?: null);
        $em->flush();
        $this->addFlash('success', '"' . $vault->getName() . '" mis à jour.');

        return $this->redirectToRoute('app_vaults');
    }

    #[Route('/vaults/{id}/archive', name: 'app_vault_archive', methods: ['POST'])]
    public function archive(
        Vault $vault,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($vault->getOwner()->getUserIdentifier() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('vault_archive_' . $vault->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $vault->setArchived(!$vault->isArchived());
        $em->flush();

        $label = $vault->isArchived() ? 'archivé' : 'restauré';
        $this->addFlash('success', 'Coffre "' . $vault->getName() . '" ' . $label . '.');

        return $this->redirectToRoute('app_vaults');
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

    #[Route('/password/{id}/edit', name: 'app_password_edit', methods: ['POST'])]
    public function editPassword(
        PasswordEntry $passwordEntry,
        Request $request,
        EntityManagerInterface $em,
        VaultRepository $vaultRepository,
        EncryptionService $encryptionService,
        FormFactoryInterface $formFactory,
    ): Response {
        $vault = $passwordEntry->getVault();
        if ($vault === null || $vault->getOwner()->getUserIdentifier() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $vaults = $vaultRepository->findBy(['owner' => $this->getUser(), 'archived' => false]);

        $form = $formFactory->createNamed('edit_password_entry', PasswordEntryType::class, $passwordEntry, [
            'vaults'           => $vaults,
            'require_password' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if ($plain !== null && $plain !== '') {
                $key = hash('sha256', $this->encryptionKey, true);
                $passwordEntry->setEncryptedPassword($encryptionService->encrypt($plain, $key));
            }

            $em->flush();
            $this->addFlash('success', '"' . $passwordEntry->getTitle() . '" mis à jour avec succès.');
        } else {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $this->addFlash('error', $errors ? implode(' ', $errors) : 'Formulaire invalide.');
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
