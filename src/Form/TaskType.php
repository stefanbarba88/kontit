<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\CompletionMode;
use App\Enum\TaskPriority;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Podaci zadatka + učesnici. Opcija client_query_builder je obavezna.
 * Opcija assignee_query_builder je opciona: bez nje (radnik) polje učesnika ne postoji i zadatak ide kreatoru.
 * Učesnici su nemapirani ("assignees"), a upisuje ih TaskService.
 */
class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Naslov'])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('client', EntityType::class, [
                'label' => 'Klijent',
                'class' => Client::class,
                'query_builder' => $options['client_query_builder'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Interni zadatak (bez klijenta)',
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Rok',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('priority', EnumType::class, [
                'label' => 'Prioritet',
                'class' => TaskPriority::class,
                'choice_label' => static fn (TaskPriority $p) => $p->label(),
            ])
            ->add('completionMode', EnumType::class, [
                'label' => 'Završetak',
                'class' => CompletionMode::class,
                'choice_label' => static fn (CompletionMode $m) => $m->label(),
                'help' => 'Kada više učesnika radi zadatak: da li svi moraju da završe ili je dovoljan jedan.',
            ]);

        if (null !== $options['assignee_query_builder']) {
            /** @var Task $task */
            $task = $options['data'];
            $builder->add('assignees', EntityType::class, [
                'label' => 'Učesnici',
                'class' => User::class,
                'query_builder' => $options['assignee_query_builder'],
                'choice_label' => 'fullName',
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'data' => $task->getAssigneeUsers() ?: ($options['default_assignees'] ?? []),
                'constraints' => [new Count(min: 1, minMessage: 'Izaberite bar jednog učesnika.')],
            ]);
            $builder->add('notifyByEmail', CheckboxType::class, [
                'label' => 'Pošalji učesnicima i na mail',
                'required' => false,
                'mapped' => false,
                'help' => 'Obaveštenje u aplikaciji dobijaju uvek. Za hitne zadatke se ova opcija preporučuje.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'assignee_query_builder' => null,
            'default_assignees' => [],
        ]);
        $resolver->setRequired('client_query_builder');
        $resolver->setAllowedTypes('client_query_builder', QueryBuilder::class);
        $resolver->setAllowedTypes('assignee_query_builder', ['null', QueryBuilder::class]);
    }
}
