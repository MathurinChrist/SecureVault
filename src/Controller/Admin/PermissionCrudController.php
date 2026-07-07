<?php

namespace App\Controller\Admin;

use App\Entity\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PermissionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Permission::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Permission')
            ->setEntityLabelInPlural('Permissions')
            ->setDefaultSort(['code' => 'ASC'])
            ->setSearchFields(['code', 'description']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code', 'Code')
            ->setHelp('Identifiant unique, ex: vault.read, user.manage');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
    }
}
