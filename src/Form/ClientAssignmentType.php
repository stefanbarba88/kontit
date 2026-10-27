<?php

namespace App\Form;

use App\Entity\Client;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Samo dodela glavnog i backup radnika (šef menja dodelu unutar svog tima).
 */
class ClientAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        ClientType::addAssignment($builder, $options['assignee_query_builder'], $options['data']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Client::class]);
        $resolver->setRequired('assignee_query_builder');
        $resolver->setAllowedTypes('assignee_query_builder', QueryBuilder::class);
    }
}
