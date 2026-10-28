<?php

namespace App\Form;

use App\Entity\AbsenceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbsenceTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var AbsenceType $type */
        $type = $options['data'];

        $builder
            ->add('name', TextType::class, ['label' => 'Naziv'])
            ->add('color', ChoiceType::class, [
                'label' => 'Boja',
                'expanded' => true,
                'choices' => TeamType::colorChoices($type->getColor()),
                'choice_attr' => static fn (string $hex) => ['data-swatch' => $hex],
                'row_attr' => ['class' => 'kt-swatches'],
            ])
            ->add('icon', ChoiceType::class, ['label' => 'Ikonica', 'required' => false, 'placeholder' => 'Bez ikonice', 'choices' => array_combine(AbsenceType::ICONS, AbsenceType::ICONS)])
            ->add('requiresApproval', CheckboxType::class, ['label' => 'Traži odobrenje', 'required' => false, 'help' => 'Bez odobrenja = samo evidencija, odmah na snazi (npr. bolovanje).'])
            ->add('countsAgainstLeave', CheckboxType::class, ['label' => 'Troši dane godišnjeg odmora', 'required' => false])
            ->add('requiresDocument', CheckboxType::class, ['label' => 'Traži dokument (npr. doznaka)', 'required' => false])
            ->add('maxDaysPerYear', IntegerType::class, ['label' => 'Najviše dana godišnje', 'required' => false, 'attr' => ['min' => 1], 'help' => 'Prazno = bez ograničenja.'])
            ->add('position', IntegerType::class, ['label' => 'Redosled', 'attr' => ['min' => 0]])
            ->add('active', CheckboxType::class, ['label' => 'Aktivna', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AbsenceType::class]);
    }
}
