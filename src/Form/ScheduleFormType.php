<?php

namespace App\Form;

use App\Entity\Schedule;
use App\Enum\NonWorkingDayShift;
use App\Enum\ScheduleType;
use App\Service\ScheduleCalculator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Raspored posla (SPEC 8g.3): tip kao segmentirana dugmad, pa samo polja tog tipa (assets/js/schedule-form.js).
 * allow_empty: override na ClientJob-u, gde prazan tip znači "kao u poslu".
 */
class ScheduleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $days = ['Poslednji dan u mesecu' => Schedule::LAST_DAY];
        for ($d = 1; $d <= 31; ++$d) {
            $days[$d.'.'] = $d;
        }
        $months = [];
        foreach (ScheduleCalculator::MONTHS as $i => $name) {
            $months[ucfirst($name)] = $i + 1;
        }

        $builder
            ->add('type', EnumType::class, [
                'label' => 'Raspored',
                'class' => ScheduleType::class,
                'choice_label' => static fn (ScheduleType $t) => $t->label(),
                'expanded' => true,
                'required' => !$options['allow_empty'],
                'placeholder' => $options['allow_empty'] ? 'Kao u poslu' : false,
            ])
            ->add('interval', IntegerType::class, ['label' => 'Na svakih (1 = svaki)', 'required' => false, 'attr' => ['min' => 1, 'max' => 365], 'row_attr' => ['data-kt-schedule-for' => 'DAILY WEEKLY MONTHLY']])
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Dan u nedelji',
                'required' => false,
                'placeholder' => false,
                'choices' => ['Ponedeljak' => 1, 'Utorak' => 2, 'Sreda' => 3, 'Četvrtak' => 4, 'Petak' => 5, 'Subota' => 6, 'Nedelja' => 7],
                'row_attr' => ['data-kt-schedule-for' => 'WEEKLY'],
            ])
            ->add('monthOfQuarter', ChoiceType::class, [
                'label' => 'Mesec posle kvartala',
                'required' => false,
                'placeholder' => false,
                'choices' => ['1. mesec (januar, april, jul, oktobar)' => 1, '2. mesec' => 2, '3. mesec' => 3],
                'row_attr' => ['data-kt-schedule-for' => 'QUARTERLY'],
            ])
            ->add('month', ChoiceType::class, ['label' => 'Mesec', 'required' => false, 'placeholder' => false, 'choices' => $months, 'row_attr' => ['data-kt-schedule-for' => 'YEARLY']])
            ->add('dayOfMonth', ChoiceType::class, ['label' => 'Dan u mesecu', 'required' => false, 'placeholder' => false, 'choices' => $days, 'row_attr' => ['data-kt-schedule-for' => 'MONTHLY QUARTERLY YEARLY']])
            ->add('workingDaysOnly', CheckboxType::class, ['label' => 'Samo radni dani (neradni se preskaču)', 'required' => false, 'row_attr' => ['data-kt-schedule-for' => 'DAILY']])
            ->add('leadDays', IntegerType::class, ['label' => 'Zadatak nastaje dana pre roka', 'required' => false, 'attr' => ['min' => 0, 'max' => 120], 'row_attr' => ['data-kt-schedule-for' => 'DAILY WEEKLY MONTHLY QUARTERLY YEARLY']])
            ->add('nonWorkingDayShift', EnumType::class, [
                'label' => 'Ako rok padne na neradni dan',
                'class' => NonWorkingDayShift::class,
                'choice_label' => static fn (NonWorkingDayShift $s) => $s->label(),
                'required' => false,
                'placeholder' => false,
                'row_attr' => ['data-kt-schedule-for' => 'WEEKLY MONTHLY QUARTERLY YEARLY'],
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['allow_empty'] = $options['allow_empty'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
            'allow_empty' => false,
            'label' => false,
            'empty_data' => static fn () => new Schedule(),
        ]);
        $resolver->setAllowedTypes('allow_empty', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'schedule';
    }
}
