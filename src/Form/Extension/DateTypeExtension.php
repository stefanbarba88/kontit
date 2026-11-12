<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Jedan date picker u aplikaciji (assets/js/datepicker.js): polje je običan tekst sa vrednošću "Y-m-d" (bez HTML5 type="date"),
 * a flatpickr ga hvata preko data-datepicker. Važi samo za widget 'single_text', ostali widgeti se ne diraju.
 */
class DateTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [DateType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('html5', static fn (Options $options, mixed $previous) => 'single_text' === $options['widget'] ? false : $previous);
        $resolver->setDefault('format', static fn (Options $options, mixed $previous) => 'single_text' === $options['widget'] ? 'yyyy-MM-dd' : $previous);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ('single_text' === $options['widget']) {
            $view->vars['attr']['data-datepicker'] ??= '';
        }
    }
}
