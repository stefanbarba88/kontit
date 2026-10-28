<?php

namespace App\Form;

use App\Entity\ClientJob;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Izmena ugovorenog posla (SPEC 8h.3c): cena (samo pojedinačni), napomena, drugačiji raspored i zaduženi, period.
 */
class ClientJobEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ClientJob $job */
        $job = $options['data'];
        $current = $job->getAssigneeOverride();

        if ($options['show_price']) {
            $builder->add('price', MoneyType::class, ['label' => 'Cena', 'currency' => 'RSD', 'required' => false, 'help' => 'Jedinica: '.$job->getJobTemplate()->getPriceUnit()->label().'.']);
        }
        $builder
            ->add('note', TextareaType::class, ['label' => 'Napomena', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('scheduleOverride', ScheduleFormType::class, ['allow_empty' => true])
            ->add('assigneeOverride', EntityType::class, [
                'label' => 'Zaduženi',
                'class' => User::class,
                'required' => false,
                'placeholder' => 'Glavni radnik klijenta ('.$job->getClient()->getPrimaryAccountant()?->getFullName().')',
                'choice_label' => 'fullName',
                'query_builder' => static fn (UserRepository $r) => $r->createQueryBuilder('u')
                    ->andWhere('u.isActive = true OR u.id = :current')->setParameter('current', $current?->getId() ?? 0)
                    ->orderBy('u.firstName')->addOrderBy('u.lastName'),
            ])
            ->add('estimatedMinutesOverride', IntegerType::class, ['label' => 'Procenjeno trajanje (min)', 'required' => false, 'attr' => ['min' => 0, 'placeholder' => (string) $job->getJobTemplate()->getEstimatedMinutes()], 'help' => 'Prazno = kao u poslu.'])
            ->add('startDate', DateType::class, ['label' => 'Početak', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endDate', DateType::class, ['label' => 'Kraj', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ClientJob::class, 'show_price' => false]);
    }
}
