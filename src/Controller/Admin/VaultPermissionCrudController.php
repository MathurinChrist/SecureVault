<?php

namespace App\Controller\Admin;

use App\Entity\VaultPermission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class VaultPermissionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VaultPermission::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Permission de coffre')
            ->setEntityLabelInPlural('Permissions de coffre')
            ->setDefaultSort(['code' => 'ASC'])
            ->setSearchFields(['code', 'name', 'description']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code', 'Code')
            ->setHelp('Niveau d\'accès : READ, WRITE ou ADMIN');
        yield TextField::new('name', 'Nom');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
    }
}
