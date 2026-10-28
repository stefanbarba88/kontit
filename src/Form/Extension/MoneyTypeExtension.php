<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Iznosi se unose i šalju kao "1.234,56" (IMask u assets/js/forms.js), pa MoneyType mora da prihvati tačku za hiljade.
 * Bez grouping-a Symfony odbija "2.500,00" kao neispravan iznos.
 */
class MoneyTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [MoneyType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('grouping', true);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-kt-money'] ??= '';
    }
}
