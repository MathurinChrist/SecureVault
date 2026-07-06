<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use App\Form\UserProfileType;
use App\Repository\ActivityLogRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        UserPasswordHasherInterface $userPasswordHasher,
        \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher,
        ActivityLogRepository $activityLogRepository,
    ): Response
    {
        $user = $this->getUser();
        
        $profileForm = $this->createForm(UserProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $imageFile = $profileForm->get('profileImageFile')->getData();

            if ($imageFile) {
                try {
                    $newFilename = $fileUploader->upload($imageFile);
                    $user->setProfileImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour !');
            return $this->redirectToRoute('app_profile');
        }

        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            // Vault data is encrypted with per-vault keys wrapped by the server master key,
            // independent of the account password — so changing the password never touches
            // or orphans stored secrets.
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $passwordForm->get('plainPassword')->getData()
                )
            );
            $entityManager->flush();

            $eventDispatcher->dispatch(new \App\Event\PasswordUpdatedEvent($user), \App\Event\PasswordUpdatedEvent::NAME);

            $this->addFlash('success', 'Mot de passe modifié avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        $sessions = $entityManager->getRepository(\App\Entity\UserSession::class)->findBy(
            ['user' => $user],
            ['lastUsedAt' => 'DESC'],
            10
        );

        /** @var \App\Entity\User $user */
        $recentActivity = $activityLogRepository->findRecentByUser($user, 30);

        return $this->render('profile/index.html.twig', [
            'profileForm'    => $profileForm->createView(),
            'passwordForm'   => $passwordForm->createView(),
            'sessions'       => $sessions,
            'recentActivity' => $recentActivity,
        ]);
    }
}
