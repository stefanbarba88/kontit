<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Podaci klijenta + dodela. Opcija assignee_query_builder (QueryBuilder radnika) je obavezna.
 * Dodela je nemapirana (primary, backup), a upisuje je ClientService koji vodi istoriju.
 */
class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Pun naziv', 'help' => 'Zvaničan naziv iz registra. Prikazuje se samo na kartici klijenta, u izveštajima i izvozima.'])
            ->add('shortName', TextType::class, ['label' => 'Kraći naziv', 'help' => 'Koristi se svuda u aplikaciji, npr. „Mlekara Ivanjica“.', 'attr' => ['maxlength' => 40]])
            ->add('pib', TextType::class, ['label' => 'PIB', 'required' => false, 'attr' => ['maxlength' => 9, 'inputmode' => 'numeric']])
            ->add('maticniBroj', TextType::class, ['label' => 'Matični broj', 'required' => false, 'attr' => ['maxlength' => 8, 'inputmode' => 'numeric']])
            ->add('pdvObveznik', CheckboxType::class, ['label' => 'PDV obveznik', 'required' => false])
            ->add('address', TextType::class, ['label' => 'Adresa', 'required' => false])
            ->add('postalCode', TextType::class, ['label' => 'Poštanski broj', 'required' => false, 'attr' => ['maxlength' => 10]])
            ->add('city', TextType::class, ['label' => 'Grad', 'required' => false])
            ->add('contactPerson', TextType::class, ['label' => 'Kontakt osoba', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Telefon', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
            ->add('startDate', DateType::class, [
                'label' => 'Klijent od',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Od kada vodimo klijenta.',
            ])
            ->add('note', TextareaType::class, ['label' => 'Napomena', 'required' => false, 'attr' => ['rows' => 3]]);

        if ($options['include_assignment']) {
            /** @var Client $client */
            $client = $options['data'];
            $this->addAssignment($builder, $options['assignee_query_builder'], $client);
        }
    }

    public static function addAssignment(FormBuilderInterface $builder, QueryBuilder $qb, Client $client): void
    {
        $common = [
            'class' => User::class,
            'mapped' => false,
            'choice_label' => static fn (User $u) => $u->getFullName(),
            'query_builder' => $qb,
        ];

        $builder
            ->add('primary', EntityType::class, $common + [
                'label' => 'Glavni zaduženi',
                'data' => $client->getPrimaryAccountant(),
                'placeholder' => '— izaberi —',
                'constraints' => [new NotNull(message: 'Izaberi glavnog zaduženog.')],
            ])
            ->add('backup', EntityType::class, $common + [
                'label' => 'Backup (zamena)',
                'data' => $client->getBackupAccountant(),
                'required' => false,
                'placeholder' => '— bez backup-a —',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
            'include_assignment' => true,
        ]);
        $resolver->setRequired('assignee_query_builder');
        $resolver->setAllowedTypes('assignee_query_builder', QueryBuilder::class);
    }
}
