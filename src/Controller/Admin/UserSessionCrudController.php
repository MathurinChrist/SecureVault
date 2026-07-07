<?php

namespace App\Controller\Admin;

use App\Entity\UserSession;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserSessionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserSession::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Session')
            ->setEntityLabelInPlural('Sessions')
            ->setDefaultSort(['lastUsedAt' => 'DESC'])
            ->setSearchFields(['ipAddress', 'location', 'userAgent']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user', 'Utilisateur');
        yield TextField::new('ipAddress', 'Adresse IP');
        yield TextField::new('location', 'Localisation');
        yield BooleanField::new('isActive', 'Active')->renderAsSwitch(false);
        yield DateTimeField::new('lastUsedAt', 'Dernière activité')
            ->setFormat('dd/MM/yyyy HH:mm:ss');
        yield DateTimeField::new('createdAt', 'Créée le')
            ->setFormat('dd/MM/yyyy HH:mm:ss')
            ->hideOnIndex();
        yield TextareaField::new('userAgent', 'User-Agent')->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isActive'))
            ->add(TextFilter::new('ipAddress'))
            ->add(EntityFilter::new('user'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }
}
