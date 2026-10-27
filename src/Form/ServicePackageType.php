<?php

namespace App\Form;

use App\Entity\JobTemplate;
use App\Entity\ServicePackage;
use App\Repository\JobTemplateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicePackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ServicePackage $package */
        $package = $options['data'];

        $builder
            ->add('name', TextType::class, ['label' => 'Naziv paketa'])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('jobTemplates', EntityType::class, [
                'label' => 'Poslovi u paketu',
                'class' => JobTemplate::class,
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'data' => $package->getJobTemplates(),
                'choice_label' => static fn (JobTemplate $j) => $j->getName().' ('.$j->getRecurrence()->label().')',
                'query_builder' => static fn (JobTemplateRepository $r) => $r->createQueryBuilder('j')->andWhere('j.isActive = true')->orderBy('j.category')->addOrderBy('j.name'),
            ])
            ->add('active', CheckboxType::class, ['label' => 'Aktivan (može da se dodeli klijentima)', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ServicePackage::class]);
    }
}
