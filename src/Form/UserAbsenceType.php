<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\UserAbsence;
use App\Enum\AbsenceReason;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Opcija user_query_builder je opciona: bez nje (radnik) odsustvo se upisuje sebi i polje radnika ne postoji.
 */
class UserAbsenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (null !== $options['user_query_builder']) {
            $builder->add('user', EntityType::class, [
                'label' => 'Radnik',
                'class' => User::class,
                'query_builder' => $options['user_query_builder'],
                'choice_label' => 'fullName',
                'placeholder' => 'Izaberi radnika',
            ]);
        }

        $builder
            ->add('startDate', DateType::class, ['label' => 'Od', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endDate', DateType::class, ['label' => 'Do (uključujući)', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('reason', EnumType::class, [
                'label' => 'Razlog',
                'class' => AbsenceReason::class,
                'choice_label' => static fn (AbsenceReason $r) => $r->label(),
            ])
            ->add('note', TextareaType::class, ['label' => 'Napomena', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => UserAbsence::class, 'user_query_builder' => null]);
    }
}
