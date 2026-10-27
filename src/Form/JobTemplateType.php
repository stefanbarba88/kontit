<?php

namespace App\Form;

use App\Entity\JobTemplate;
use App\Enum\CompletionMode;
use App\Enum\JobCategory;
use App\Enum\PriceUnit;
use App\Enum\Recurrence;
use App\Enum\TaskPriority;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var JobTemplate $job */
        $job = $options['data'];
        $lines = implode("\n", array_map(static fn ($i) => $i->getText(), $job->getChecklistItems()->toArray()));

        $builder
            ->add('name', TextType::class, ['label' => 'Naziv posla'])
            ->add('category', EnumType::class, ['label' => 'Kategorija', 'class' => JobCategory::class, 'choice_label' => static fn (JobCategory $c) => $c->label()])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('recurrence', EnumType::class, ['label' => 'Ponavljanje', 'class' => Recurrence::class, 'choice_label' => static fn (Recurrence $r) => $r->label()])
            ->add('dueDay', IntegerType::class, ['label' => 'Dan roka', 'required' => false, 'help' => 'Dan u mesecu (nedeljno: dan u nedelji, 1 = ponedeljak). Kvartalno: dan u mesecu posle kvartala.', 'attr' => ['min' => 1, 'max' => 31]])
            ->add('dueMonth', IntegerType::class, ['label' => 'Mesec roka', 'required' => false, 'help' => 'Samo za godišnje poslove (1–12).', 'attr' => ['min' => 1, 'max' => 12]])
            ->add('generateDaysBefore', IntegerType::class, ['label' => 'Zadatak nastaje dana pre roka', 'attr' => ['min' => 0, 'max' => 120]])
            ->add('defaultPriority', EnumType::class, ['label' => 'Podrazumevani prioritet', 'class' => TaskPriority::class, 'choice_label' => static fn (TaskPriority $p) => $p->label()])
            ->add('completionMode', EnumType::class, ['label' => 'Režim završetka', 'class' => CompletionMode::class, 'choice_label' => static fn (CompletionMode $m) => $m->label()])
            ->add('estimatedMinutes', IntegerType::class, ['label' => 'Procenjeno trajanje (min)', 'attr' => ['min' => 0]])
            ->add('priceUnit', EnumType::class, ['label' => 'Jedinica cene', 'help' => 'Cenu upisuješ tek kad posao dodaš klijentu.', 'class' => PriceUnit::class, 'choice_label' => static fn (PriceUnit $u) => $u->label()])
            ->add('checklist', TextareaType::class, [
                'label' => 'Checklista',
                'mapped' => false,
                'required' => false,
                'data' => $lines,
                'attr' => ['rows' => 6],
                'help' => 'Jedna stavka po redu. Kopira se u svaki generisani zadatak.',
            ])
            ->add('active', CheckboxType::class, ['label' => 'Aktivan', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JobTemplate::class]);
    }
}
