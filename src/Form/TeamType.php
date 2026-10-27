<?php

namespace App\Form;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Team $team */
        $team = $options['data'];

        $builder
            ->add('name', TextType::class, ['label' => 'Naziv tima'])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('color', ChoiceType::class, [
                'label' => 'Boja',
                'help' => 'Koristi se za badge tima.',
                'expanded' => true,
                'choices' => self::colorChoices($team->getColor()),
                'choice_attr' => static fn (string $hex) => ['data-swatch' => $hex],
                'row_attr' => ['class' => 'kt-swatches'],
            ])
            ->add('leader', EntityType::class, [
                'label' => 'Šef tima',
                'class' => User::class,
                'mapped' => false,
                'data' => $team->getLeader(),
                'placeholder' => '— izaberite šefa —',
                'choice_label' => static fn (User $u) => $u->getFullName().' ('.$u->getEmail().')',
                'query_builder' => static fn (UserRepository $r) => $r->createLeaderChoiceQueryBuilder($team->getLeader()),
                'constraints' => [new NotNull(message: 'Izaberite šefa tima.')],
                'help' => 'Prikazani su samo korisnici sa rolom šef. Promena šefa se beleži u istoriji.',
            ]);
    }

    /**
     * Osam unapred podešenih nijansi (DESIGN.md 3.6). Boja ranije izabrana van liste ostaje izbor dok se ne promeni.
     *
     * @return array<string, string>
     */
    public static function colorChoices(?string $current): array
    {
        $choices = ['Plava' => '#0089FF', 'Indigo' => '#5B6CFF', 'Ljubičasta' => '#8B5CF6', 'Tirkizna' => '#14B8A6', 'Zelena' => '#22C55E', 'Žuta' => '#F59E0B', 'Ružičasta' => '#F43F5E', 'Siva' => '#64748B'];
        if (null !== $current && !in_array(strtoupper($current), array_map('strtoupper', $choices), true)) {
            $choices['Prethodna boja'] = $current;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Team::class]);
    }
}
