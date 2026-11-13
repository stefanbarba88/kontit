<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\CompletionMode;
use App\Enum\ReviewMode;
use App\Enum\TaskPriority;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Zadatak (SPEC 8i.3): klijent ili interni zadatak, ugovoreni posao klijenta (opciono), učesnici i checklist.
 * Opcija client_query_builder je obavezna. Bez assignee_query_builder (radnik) polje učesnika ne postoji i zadatak ide kreatoru.
 * lock_client: klijent je zadat (ulaz sa kartice klijenta) i ne menja se.
 * Učesnici ("assignees"), vrsta ("scope") i checklist su nemapirani; upisuje ih TaskController/TaskService.
 */
class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Task $task */
        $task = $options['data'];
        $isNew = null === $task->getId();

        if (!$options['lock_client']) {
            $builder->add('scope', ChoiceType::class, [
                'label' => 'Zadatak je',
                'mapped' => false,
                'expanded' => true,
                'choices' => ['Za klijenta' => 'client', 'Interni (bez klijenta)' => 'internal'],
                'data' => null !== $task->getClient() || $isNew ? 'client' : 'internal',
            ]);
        }

        $builder
            ->add('title', TextType::class, ['label' => 'Naslov'])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('client', EntityType::class, [
                'label' => 'Klijent',
                'class' => Client::class,
                'query_builder' => $options['client_query_builder'],
                'choice_label' => static fn (Client $c) => $c->getShortName().($c->getPib() ? ' · PIB '.$c->getPib() : ''),
                // pretraga i po punom nazivu (Tom Select, data-kt-search)
                'choice_attr' => static fn (Client $c) => ['data-data' => json_encode(['search' => $c->getName().' '.$c->getPib()])],
                'required' => false,
                'placeholder' => '— izaberi klijenta —',
                'disabled' => $options['lock_client'],
                'attr' => ['data-kt-search' => '', 'data-kt-task-client' => ''],
            ])
            ->add('clientJob', EntityType::class, [
                'label' => 'Posao',
                'class' => ClientJob::class,
                'query_builder' => $options['job_query_builder'],
                'choice_label' => static fn (ClientJob $j) => $j->getJobTemplate()->getName(),
                'choice_attr' => static fn (ClientJob $j) => ['data-client' => $j->getClient()->getId(), 'data-requires-approval' => $j->getJobTemplate()->requiresApproval() ? '1' : '0'],
                'required' => false,
                'placeholder' => 'Bez posla (samostalan zadatak)',
                'help' => 'Ugovoreni posao klijenta. Izbor posla popunjava checklist, prioritet i procenu trajanja.',
                'attr' => ['data-kt-task-job' => '', 'data-native' => ''],
                // generisan zadatak ostaje vezan za svoj posao i period
                'disabled' => $task->isGeneratedAutomatically(),
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Rok',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-kt-workday-warning' => ''],
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

        // checklist se unosi pri kreiranju; posle se menja na stranici zadatka (pravila za ponovno otvaranje i dodelu stavki)
        if ($isNew) {
            $builder->add('checklist', ChecklistCollectionType::class, ['mapped' => false, 'entry_options' => ['data_class' => null], 'help' => 'Stavke možeš i kasnije da dodaješ na stranici zadatka.']);
        }

        if (null !== $options['assignee_query_builder']) {
            $builder->add('assignees', EntityType::class, [
                'label' => 'Učesnici',
                'class' => User::class,
                'query_builder' => $options['assignee_query_builder'],
                'choice_label' => static fn (User $u) => $u->getFullName().($u->getPosition() ? ' ('.$u->getPosition()->getName().')' : ''),
                'choice_attr' => static fn (User $u) => ['data-user' => $u->getId(), 'data-review' => $u->getPosition()?->requiresTaskReview() ? '1' : '0'],
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'data' => $task->getAssigneeUsers() ?: ($options['default_assignees'] ?? []),
                'constraints' => [new Count(min: 1, minMessage: 'Izaberi bar jednog učesnika.')],
            ]);
            $builder->add('notifyByEmail', CheckboxType::class, [
                'label' => 'Pošalji učesnicima i na mejl',
                'required' => false,
                'mapped' => false,
                'help' => 'Obaveštenje u aplikaciji dobijaju uvek. Za hitne zadatke se ova opcija preporučuje.',
            ]);
        }

        // način provere: polje postoji samo ako kreator sme da bira (kreator čija pozicija traži proveru ga ne vidi, server forsira AUTO)
        if ([] !== $options['review_modes']) {
            $builder->add('reviewMode', ChoiceType::class, [
                'label' => 'Provera',
                'mapped' => false,
                'choices' => array_combine(array_map(static fn (ReviewMode $m) => $m->label(), $options['review_modes']), $options['review_modes']),
                'choice_value' => static fn (?ReviewMode $m) => $m?->value,
                'data' => $task->getReviewMode(),
                'attr' => ['data-native' => '', 'data-kt-review-mode' => ''],
                'help' => 'Ko proverava završen zadatak. „Posebna potvrda“ ide na stranicu Posebne potvrde izabranih admina.',
            ]);
            $builder->add('reviewApprovers', EntityType::class, [
                'label' => 'Ko potvrđuje',
                'class' => User::class,
                'multiple' => true,
                'required' => false,
                'mapped' => false,
                'query_builder' => $options['review_approver_query_builder'],
                'choice_label' => static fn (User $u) => $u->getFullName(),
                'data' => $task->getRequiredApprovers()->toArray(),
                'row_attr' => ['data-kt-review-approvers' => ''],
                'help' => 'Najmanje jedan admin ili superadmin.',
            ]);
        }

        // interni zadatak nema klijenta ni posao, a "za klijenta" traži klijenta
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            /** @var Task $task */
            $task = $event->getData();
            if (!$form->has('scope')) {
                return;
            }
            if ('internal' === $form->get('scope')->getData()) {
                $task->setClient(null);
                $task->linkJob(null);
            } elseif (null === $task->getClient()) {
                $form->get('client')->addError(new \Symfony\Component\Form\FormError('Izaberi klijenta ili označi da je zadatak interni.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'assignee_query_builder' => null,
            'default_assignees' => [],
            'lock_client' => false,
            'review_modes' => [],
            'review_approver_query_builder' => null,
        ]);
        $resolver->setRequired(['client_query_builder', 'job_query_builder']);
        $resolver->setAllowedTypes('client_query_builder', QueryBuilder::class);
        $resolver->setAllowedTypes('job_query_builder', QueryBuilder::class);
        $resolver->setAllowedTypes('assignee_query_builder', ['null', QueryBuilder::class]);
    }
}
