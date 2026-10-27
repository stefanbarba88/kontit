<?php

namespace App\Form;

use App\Entity\ClientJob;
use App\Entity\JobTemplate;
use App\Repository\JobTemplateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ad hoc posao klijenta. Cenu unosi samo admin (opcija show_price).
 */
class ClientJobType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('jobTemplate', EntityType::class, [
                'label' => 'Posao',
                'class' => JobTemplate::class,
                'placeholder' => '— izaberite posao —',
                'choice_label' => static fn (JobTemplate $j) => $j->getName().' ('.$j->getRecurrence()->label().')',
                'query_builder' => static fn (JobTemplateRepository $r) => $r->createQueryBuilder('j')->andWhere('j.isActive = true')->orderBy('j.category')->addOrderBy('j.name'),
            ])
            ->add('startDate', DateType::class, ['label' => 'Početak', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endDate', DateType::class, ['label' => 'Kraj', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable']);

        if ($options['show_price']) {
            $builder->add('price', MoneyType::class, ['label' => 'Cena', 'currency' => 'RSD', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ClientJob::class, 'show_price' => false]);
    }
}
