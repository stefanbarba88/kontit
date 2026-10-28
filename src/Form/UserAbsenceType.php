<?php

namespace App\Form;

use App\Entity\AbsenceType;
use App\Entity\User;
use App\Entity\UserAbsence;
use App\Repository\AbsenceTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Zahtev za odsustvo (SPEC 8j.2). Polje "Radnik" postoji samo za šefa i admina (user_query_builder).
 * Radni dani, preostali godišnji i odsutne kolege se prikazuju u letu (assets/js/absence-form.js).
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
                'attr' => ['data-kt-absence-user' => ''],
            ]);
        }

        $builder
            ->add('type', EntityType::class, [
                'label' => 'Vrsta odsustva',
                'class' => AbsenceType::class,
                'choice_label' => 'name',
                'placeholder' => '— izaberi —',
                'query_builder' => static fn (AbsenceTypeRepository $r) => $r->createQueryBuilder('t')->andWhere('t.isActive = true')->orderBy('t.position')->addOrderBy('t.name'),
                'choice_attr' => static fn (AbsenceType $t) => [
                    'data-leave' => $t->isCountsAgainstLeave() ? '1' : '0',
                    'data-approval' => $t->isRequiresApproval() ? '1' : '0',
                    'data-document' => $t->isRequiresDocument() ? '1' : '0',
                ],
                'attr' => ['data-kt-absence-type' => '', 'data-native' => ''],
            ])
            ->add('startDate', DateType::class, ['label' => 'Od', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['data-kt-absence-from' => '']])
            ->add('endDate', DateType::class, ['label' => 'Do (uključujući)', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['data-kt-absence-to' => '']])
            ->add('halfDay', CheckboxType::class, ['label' => 'Pola dana', 'required' => false, 'help' => 'Samo za odsustvo od jednog dana.', 'attr' => ['data-kt-absence-half' => '']])
            ->add('note', TextareaType::class, ['label' => 'Napomena', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => UserAbsence::class, 'user_query_builder' => null]);
    }
}
