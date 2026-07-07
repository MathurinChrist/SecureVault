<?php

namespace App\Controller\Admin;

use App\Entity\Tag;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Étiquette')
            ->setEntityLabelInPlural('Étiquettes')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom');
        yield TextField::new('color', 'Couleur')
            ->setHelp('Code couleur hexadécimal, ex: #2f7d5b');
        yield IntegerField::new('passwordEntries', 'Nb entrées')
            ->formatValue(static fn ($v, $e) => $e?->getPasswordEntries()->count())
            ->hideOnForm();
        yield IntegerField::new('vaults', 'Nb coffres')
            ->formatValue(static fn ($v, $e) => $e?->getVaults()->count())
            ->hideOnForm();
    }
}
