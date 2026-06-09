<?php

namespace App\Form;

use App\Entity\Vault;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class VaultType extends AbstractType
{
    private const INPUT_CLASS = 'w-full bg-slate-800/50 border border-white/10 rounded-xl px-4 py-3 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition text-sm';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => [
                    'class'       => self::INPUT_CLASS,
                    'placeholder' => 'ex: Travail, Personnel, Finance...',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser 255 caractères.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr'     => [
                    'class'       => self::INPUT_CLASS,
                    'placeholder' => 'Description optionnelle...',
                    'rows'        => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Vault::class]);
    }
}
