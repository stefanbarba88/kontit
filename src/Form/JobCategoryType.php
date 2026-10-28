<?php

namespace App\Form;

use App\Entity\JobCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var JobCategory $category */
        $category = $options['data'];

        $builder
            ->add('name', TextType::class, ['label' => 'Naziv', 'attr' => ['maxlength' => 80]])
            ->add('color', ChoiceType::class, [
                'label' => 'Boja',
                'expanded' => true,
                'choices' => TeamType::colorChoices($category->getColor()),
                'choice_attr' => static fn (string $hex) => ['data-swatch' => $hex],
                'row_attr' => ['class' => 'kt-swatches'],
            ])
            ->add('icon', ChoiceType::class, [
                'label' => 'Ikonica',
                'required' => false,
                'placeholder' => 'Bez ikonice',
                'choices' => array_combine(JobCategory::ICONS, JobCategory::ICONS),
                'attr' => ['data-kt-icon-select' => ''],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobCategory::class]);
    }
}
