<?php

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class TeamMemberType extends AbstractType
{
    /**
     * Opcija warnings: [userId => tekst upozorenja] za radnike koji su već u nekom timu (prikazuje se u potvrdi premeštanja).
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['warnings' => []]);
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', EntityType::class, [
            'label' => 'Radnik',
            'class' => User::class,
            'placeholder' => '— izaberite radnika —',
            'choice_label' => static fn (User $u) => $u->getFullName(),
            'query_builder' => static fn (UserRepository $r) => $r->createActiveChoiceQueryBuilder(),
            'choice_attr' => static fn (User $u) => isset($options['warnings'][$u->getId()]) ? ['data-warning' => $options['warnings'][$u->getId()]] : [],
            'constraints' => [new NotNull(message: 'Izaberite radnika.')],
            'help' => 'Ako je radnik u drugom timu, biće premešten. Njegovi klijenti i zadaci idu sa njim.',
        ]);
    }
}
