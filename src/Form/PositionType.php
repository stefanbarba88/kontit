<?php

namespace App\Form;

use App\Entity\Position;
use App\Enum\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Position $position */
        $position = $options['data'];

        $builder
            ->add('name', TextType::class, ['label' => 'Naziv', 'attr' => ['maxlength' => 100]])
            ->add('description', TextType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['maxlength' => 255]])
            ->add('color', ChoiceType::class, [
                'label' => 'Boja',
                'expanded' => true,
                'choices' => TeamType::colorChoices($position->getColor()),
                'choice_attr' => static fn (string $hex) => ['data-swatch' => $hex],
                'row_attr' => ['class' => 'kt-swatches'],
            ])
            ->add('requiresTaskReview', CheckboxType::class, [
                'label' => 'Zadaci ove pozicije traže proveru',
                'required' => false,
                'help' => 'Završen zadatak radnika na ovoj poziciji potvrđuje šef tima (ili admin). Bez kvačice se zadatak završava odmah.',
            ])
            ->add('monthlyCapacityHours', IntegerType::class, [
                'label' => 'Mesečni kapacitet (sati)',
                'required' => false,
                'help' => 'Za analitiku opterećenja. Važi ako korisnik nema svoj kapacitet; bez vrednosti važi opšti iz Finansija.',
                'attr' => ['min' => 1, 'max' => 400],
            ])
            ->add('suggestedAccessRole', ChoiceType::class, [
                'label' => 'Predlog prava pristupa',
                'required' => false,
                'placeholder' => 'Bez predloga',
                'choices' => [Role::RADNIK->label() => Role::RADNIK, Role::SEF->label() => Role::SEF, Role::ADMIN->label() => Role::ADMIN],
                'help' => 'Samo predlog u formi korisnika. Pozicija ne daje prava i ništa se ne dodeljuje automatski.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Position::class]);
    }
}
