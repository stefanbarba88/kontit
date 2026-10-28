<?php

namespace App\Form;

use App\Entity\JobTemplateChecklistItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Jedan red checklist editora: tekst + skrivena pozicija. data_class se menja za checklist zadatka.
 */
class ChecklistEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextType::class, ['label' => false, 'attr' => ['placeholder' => 'Nova stavka…', 'maxlength' => 255, 'data-kt-checklist-input' => '', 'aria-label' => 'Tekst stavke']])
            ->add('position', HiddenType::class, ['attr' => ['data-kt-checklist-position' => '']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobTemplateChecklistItem::class, 'label' => false]);
    }
}
