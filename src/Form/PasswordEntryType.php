<?php

namespace App\Form;

use App\Entity\PasswordEntry;
use App\Entity\Vault;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordEntryType extends AbstractType
{
    private const INPUT_CLASS  = 'w-full bg-slate-800/50 border border-white/10 rounded-xl px-4 py-3 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition text-sm';
    private const SELECT_CLASS = 'w-full bg-slate-800/50 border border-white/10 rounded-xl px-4 py-3 text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 transition text-sm';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requirePassword = $options['require_password'];

        $builder
            ->add('vault', EntityType::class, [
                'class'       => Vault::class,
                'choice_label' => 'name',
                'choices'     => $options['vaults'],
                'placeholder' => '-- Sélectionner un coffre --',
                'attr'        => ['class' => self::SELECT_CLASS],
                'constraints' => [new NotBlank(message: 'Veuillez sélectionner un coffre.')],
            ])
            ->add('title', TextType::class, [
                'attr' => [
                    'class'       => self::INPUT_CLASS,
                    'placeholder' => 'ex: Gmail, GitHub, Netflix...',
                ],
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire.')],
            ])
            ->add('username', TextType::class, [
                'required' => false,
                'attr'     => [
                    'class'       => self::INPUT_CLASS,
                    'placeholder' => "email ou nom d'utilisateur",
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped'       => false,
                'always_empty' => false,
                'required'     => $requirePassword,
                'attr'         => [
                    'class'       => self::INPUT_CLASS . ' pr-20',
                    'placeholder' => $requirePassword
                        ? '••••••••'
                        : 'Laisser vide pour conserver le mot de passe actuel',
                ],
                'constraints' => $requirePassword
                    ? [new NotBlank(message: 'Le mot de passe est obligatoire.')]
                    : [],
            ])
            ->add('url', TextType::class, [
                'required' => false,
                'attr'     => [
                    'class'       => self::INPUT_CLASS,
                    'placeholder' => 'https://...',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'attr'     => [
                    'class'       => self::INPUT_CLASS,
                    'placeholder' => 'Notes supplémentaires...',
                    'rows'        => 3,
                ],
            ])
            ->add('favorite', CheckboxType::class, [
                'required' => false,
                'label'    => 'Marquer comme favori',
                'attr'     => ['class' => 'h-4 w-4 rounded border-white/20 bg-slate-800 text-indigo-500 focus:ring-indigo-500'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'       => PasswordEntry::class,
            'vaults'           => [],
            'require_password' => true,
        ]);
    }
}
