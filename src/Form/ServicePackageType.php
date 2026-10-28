<?php

namespace App\Form;

use App\Entity\JobTemplate;
use App\Entity\ServicePackage;
use App\Repository\JobTemplateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServicePackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Naziv paketa'])
            ->add('description', TextareaType::class, ['label' => 'Opis', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('price', MoneyType::class, ['label' => 'Predložena mesečna cena', 'currency' => 'RSD', 'required' => false, 'help' => 'Samo predlog pri dodeli klijentu; dogovorena cena se upisuje kod svakog klijenta.'])
            ->add('active', CheckboxType::class, ['label' => 'Aktivan (može da se dodeli klijentima)', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ServicePackage::class]);
    }
}
