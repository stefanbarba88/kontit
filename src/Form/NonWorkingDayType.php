<?php

namespace App\Form;

use App\Entity\NonWorkingDay;
use App\Enum\NonWorkingDayType as Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NonWorkingDayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'label' => 'Vrsta',
                'class' => Type::class,
                'choice_label' => static fn (Type $t) => $t->label(),
                'expanded' => true,
            ])
            ->add('name', TextType::class, ['label' => 'Naziv', 'attr' => ['placeholder' => 'npr. Nova godina']])
            ->add('dateFrom', DateType::class, ['label' => 'Od', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('dateTo', DateType::class, ['label' => 'Do (uključujući)', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false, 'help' => 'Prazno = jedan dan. Kod praznika koji se ponavlja važe samo dan i mesec.'])
            ->add('note', TextareaType::class, ['label' => 'Napomena', 'required' => false, 'attr' => ['rows' => 2]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NonWorkingDay::class]);
    }
}
