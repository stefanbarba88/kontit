<?php

namespace App\Form;

use App\Entity\JobCategory;
use App\Entity\JobTemplate;
use App\Enum\CompletionMode;
use App\Enum\PriceUnit;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Repository\UserRepository;
use App\Repository\JobCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobTemplateType extends AbstractType
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var JobTemplate $job */
        $job = $options['data'];
        $current = $job->getCategory();

        $builder
            ->add('name', TextType::class, ['label' => 'Naziv posla'])
            ->add('category', EntityType::class, [
                'label' => 'Kategorija',
                'class' => JobCategory::class,
                'placeholder' => '— izaberi —',
                'choice_label' => 'name',
                // aktivne kategorije, plus trenutna i kad je deaktivirana
                'query_builder' => static fn (JobCategoryRepository $r) => $r->createQueryBuilder('c')
                    ->andWhere('c.isActive = true OR c.id = :current')
                    ->setParameter('current', $current?->getId() ?? 0)
                    ->orderBy('c.position', 'ASC'),
            ])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('schedule', ScheduleFormType::class)
            ->add('checklistItems', ChecklistCollectionType::class, ['help' => 'Kopira se u svaki zadatak koji nastane iz ovog posla.'])
            ->add('defaultPriority', EnumType::class, ['label' => 'Podrazumevani prioritet', 'class' => TaskPriority::class, 'choice_label' => static fn (TaskPriority $p) => $p->label()])
            ->add('completionMode', EnumType::class, ['label' => 'Režim završetka', 'class' => CompletionMode::class, 'choice_label' => static fn (CompletionMode $m) => $m->label()])
            ->add('estimatedMinutes', IntegerType::class, ['label' => 'Procenjeno trajanje (min)', 'attr' => ['min' => 0]])
            ->add('priceUnit', EnumType::class, ['label' => 'Jedinica cene', 'class' => PriceUnit::class, 'choice_label' => static fn (PriceUnit $u) => $u->label()])
            ->add('defaultPrice', MoneyType::class, ['label' => 'Predložena cena', 'currency' => 'RSD', 'required' => false, 'help' => 'Samo predlog kad se posao ugovara pojedinačno; prava cena se upisuje na klijentu.'])
            ->add('requiresApproval', CheckboxType::class, [
                'label' => 'Traži potvrdu',
                'required' => false,
                'help' => 'Za posebne poslove. Zadatke iz ovog posla mora da potvrdi izabrana osoba, bez obzira na poziciju radnika.',
                'attr' => ['data-kt-approval-toggle' => ''],
            ])
            ->add('approvers', EntityType::class, [
                'label' => 'Ko potvrđuje',
                'class' => User::class,
                'multiple' => true,
                'required' => false,
                'query_builder' => $this->users->createReviewApproverQueryBuilder($job->getApprovers()),
                'choice_label' => static fn (User $u) => $u->getFullName().($u->isActive() ? '' : ' (neaktivan)'),
                'help' => 'Admini i superadmini. Najmanje jedan je obavezan kad je potvrda uključena.',
                'row_attr' => ['data-kt-approvers-row' => ''],
            ])
            ->add('active', CheckboxType::class, ['label' => 'Aktivan', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobTemplate::class]);
    }
}
