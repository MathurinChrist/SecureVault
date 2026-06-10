<?php

namespace App\Form;

use App\Entity\Vault;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VaultType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du coffre',
                'attr' => ['placeholder' => 'Ex: Personnel, Travail, Famille']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (Optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'À quoi sert ce coffre ?']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver.setDefaults([
            'data_class' => Vault::class,
        ]);
    }
}
