<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Position;
use App\Enum\Role;
use App\Repository\PositionRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Admin forma za korisnika. Opcije: role_choices (list<Role>), require_password.
 */
class UserType extends AbstractType
{
    public function __construct(private readonly PositionRepository $positions)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Ime'])
            ->add('lastName', TextType::class, ['label' => 'Prezime'])
            ->add('email', EmailType::class, ['label' => 'Email (login)'])
            ->add('role', EnumType::class, [
                'label' => 'Prava pristupa',
                'class' => Role::class,
                'choices' => $options['role_choices'],
                'choice_label' => static fn (Role $r) => $r->label(),
                'mapped' => false,
                'data' => $options['data']->getHighestRole(),
                'help' => 'Prava pristupa određuju šta korisnik sme u aplikaciji. Šef tima dobija prava šefa automatski kada se postavi za šefa tima.',
                'attr' => ['data-confirm-field-text' => 'Promena prava pristupa menja šta korisnik sme u aplikaciji.', 'data-confirm-field-message' => 'Promeniti prava pristupa korisnika?'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $options['require_password'],
                'first_options' => ['label' => $options['require_password'] ? 'Lozinka' : 'Nova lozinka', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Ponovi lozinku', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Lozinke se ne poklapaju.',
                'help' => $options['require_password'] ? null : 'Ostavi prazno da lozinka ostane ista.',
                'constraints' => array_filter([
                    $options['require_password'] ? new NotBlank(message: 'Unesi lozinku.') : null,
                    new Length(min: 8, minMessage: 'Lozinka mora imati najmanje {{ limit }} karaktera.', max: 4096),
                ]),
            ])
            ->add('phone', TextType::class, ['label' => 'Telefon', 'required' => false])
            ->add('address', TextType::class, ['label' => 'Adresa', 'required' => false])
            ->add('city', TextType::class, ['label' => 'Grad', 'required' => false])
            ->add('postalCode', TextType::class, ['label' => 'Poštanski broj', 'required' => false])
            ->add('position', EntityType::class, [
                'class' => Position::class,
                'label' => 'Pozicija',
                'required' => false,
                'placeholder' => 'Bez pozicije',
                'choices' => $this->positions->findOrdered(true, $options['data']->getPosition()),
                'choice_label' => static fn (Position $p) => $p->getName().($p->isActive() ? '' : ' (neaktivna)'),
                'choice_attr' => static fn (Position $p) => [
                    'data-data' => json_encode(['color' => $p->getColor()]),
                    'data-suggested-role' => $p->getSuggestedAccessRole()?->value ?? '',
                ],
                'help' => 'Nivo zaposlenog. Pozicija NE daje prava pristupa, ona se biraju posebno. Promena pozicije se beleži u istoriji.',
                'attr' => ['data-kt-position-select' => '', 'data-confirm-field-text' => 'Promena pozicije se beleži u istoriji korisnika; prava pristupa se ne menjaju.', 'data-confirm-field-message' => 'Promeniti poziciju korisnika?', 'data-confirm-reason' => 'optional'],
            ])
            ->add('employedAt', DateType::class, [
                'label' => 'Datum zaposlenja',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('internalNote', TextareaType::class, [
                'label' => 'Interna beleška',
                'required' => false,
                'help' => 'Vidi samo admin.',
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'role_choices' => [Role::RADNIK, Role::SEF],
            'require_password' => false,
        ]);
        $resolver->setAllowedTypes('role_choices', 'array');
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
