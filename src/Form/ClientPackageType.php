<?php

namespace App\Form;

use App\Entity\ClientPackage;
use App\Entity\ServicePackage;
use App\Repository\ServicePackageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientPackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('package', EntityType::class, [
                'label' => 'Paket',
                'class' => ServicePackage::class,
                'placeholder' => '— izaberi paket —',
                'choice_label' => static fn (ServicePackage $p) => $p->getName(),
                'query_builder' => static fn (ServicePackageRepository $r) => $r->createQueryBuilder('p')->andWhere('p.isActive = true')->orderBy('p.name'),
            ])
            ->add('agreedPrice', MoneyType::class, ['label' => 'Mesečna cena paketa za ovog klijenta', 'currency' => 'RSD', 'help' => 'Cena paketa se dogovara sa svakim klijentom posebno.'])
            ->add('discountPercent', NumberType::class, ['label' => 'Popust (%)', 'required' => false, 'scale' => 2])
            ->add('startDate', DateType::class, ['label' => 'Početak', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('note', TextareaType::class, ['label' => 'Napomena', 'required' => false, 'attr' => ['rows' => 2]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ClientPackage::class]);
    }
}
