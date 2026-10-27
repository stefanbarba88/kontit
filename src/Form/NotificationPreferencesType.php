<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\NotificationType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Koje vrste obaveštenja korisnik uvek želi i na mail (User::emailNotifications). U aplikaciji dolaze sva.
 */
class NotificationPreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach (NotificationType::cases() as $type) {
            $choices[$type->label()] = $type->value;
        }

        $builder->add('emailNotifications', ChoiceType::class, [
            'label' => 'Uvek pošalji i na mail kada:',
            'choices' => $choices,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
