<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => ['placeholder' => '••••••••'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['placeholder' => '••••••••'],
                ],
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Entrez un mot de passe'),
                    new Length(min: 12, minMessage: 'Au moins {{ limit }} caractères', max: 4096),
                    new NotCompromisedPassword(message: 'Ce mot de passe est apparu dans une fuite de données. Choisissez-en un autre.', skipOnError: true),
                ],
            ])
        ;
    }
}
