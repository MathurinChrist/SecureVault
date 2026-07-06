<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\SharedVaultRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Entity\Role;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[IsGranted('ROLE_ADMIN')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly SharedVaultRepository $sharedVaultRepository,
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email', 'firstName', 'lastName']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email', 'Email');
        yield TextField::new('firstName', 'Prénom');
        yield TextField::new('lastName', 'Nom');
        yield TextField::new('plainPassword', 'Mot de passe')
            ->setFormType(PasswordType::class)
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->onlyOnForms()
            ->setHelp($pageName === Crud::PAGE_EDIT ? 'Laisser vide pour ne pas modifier.' : '');
        yield ChoiceField::new('roles', 'Rôles')
            ->setChoices([
                'Utilisateur'  => Role::ROLE_USER,
                'Manager'      => Role::ROLE_MANAGER,
                'Administrateur' => Role::ROLE_ADMIN,
            ])
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->hideOnIndex();
        yield BooleanField::new('emailVerified', 'Email vérifié')->renderAsSwitch(false);
        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email'))
            ->add(BooleanFilter::new('emailVerified'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->hashPasswordIfProvided($entity);
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        $this->hashPasswordIfProvided($entity);
        parent::updateEntity($em, $entity);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }

        // These rows reference the user via foreign keys that are neither
        // ON DELETE CASCADE in the schema nor mapped as cascading Doctrine
        // relations on User, so they must be cleared explicitly first.
        foreach ($this->sharedVaultRepository->findAsSenderOrRecipient($entityInstance) as $share) {
            $entityManager->remove($share);
        }
        foreach ($this->resetPasswordRequestRepository->findBy(['user' => $entityInstance]) as $resetRequest) {
            $entityManager->remove($resetRequest);
        }
        $entityManager->flush();

        parent::deleteEntity($entityManager, $entityInstance);
    }

    private function hashPasswordIfProvided(mixed $entity): void
    {
        if (!$entity instanceof User) {
            return;
        }

        $plain = $entity->getPlainPassword();
        if ($plain !== null && $plain !== '') {
            $entity->setPassword($this->hasher->hashPassword($entity, $plain));
            $entity->eraseCredentials();
        }
    }
}
