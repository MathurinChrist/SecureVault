<?php

namespace App\Controller\Admin;

use App\Entity\SharedVault;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SharedVaultCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SharedVault::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Partage de coffre')
            ->setEntityLabelInPlural('Partages de coffre')
            ->setDefaultSort(['sharedAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('vault', 'Coffre');
        yield AssociationField::new('sender', 'Émetteur');
        yield AssociationField::new('recipient', 'Destinataire');
        yield AssociationField::new('permission', 'Permission');
        yield BooleanField::new('accepted', 'Accepté')->renderAsSwitch(false);
        yield DateTimeField::new('sharedAt', 'Partagé le')
            ->setFormat('dd/MM/yyyy HH:mm');
        yield DateTimeField::new('acceptedAt', 'Accepté le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('accepted'))
            ->add(EntityFilter::new('vault'))
            ->add(EntityFilter::new('sender'))
            ->add(EntityFilter::new('recipient'))
            ->add(EntityFilter::new('permission'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }
}
