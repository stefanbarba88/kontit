<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class OwnPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Trenutna lozinka',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new NotBlank(message: 'Unesite trenutnu lozinku.'), new UserPassword(message: 'Trenutna lozinka nije ispravna.')],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Nova lozinka', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Ponovite novu lozinku', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Lozinke se ne poklapaju.',
                'constraints' => [
                    new NotBlank(message: 'Unesite novu lozinku.'),
                    new Length(min: 8, minMessage: 'Lozinka mora imati najmanje {{ limit }} karaktera.', max: 4096),
                ],
            ]);
    }
}
