<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * confirm_attrs() ispisuje data atribute za globalni SweetAlert mehanizam (assets/js/confirm.js).
 * Primer: <button {{ confirm_attrs('Deaktivirati?', type='danger', button='Da, deaktiviraj') }}>
 */
class ConfirmExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('confirm_attrs', $this->attrs(...), ['is_safe' => ['html']])];
    }

    public function attrs(string $message, ?string $text = null, string $type = 'warning', ?string $button = null, bool $reason = false, ?string $typeName = null): string
    {
        $attrs = ['data-confirm' => $message, 'data-confirm-type' => $type];
        if (null !== $text) {
            $attrs['data-confirm-text'] = $text;
        }
        if (null !== $button) {
            $attrs['data-confirm-button'] = $button;
        }
        if ($reason) {
            $attrs['data-confirm-reason'] = 'required';
        }
        if (null !== $typeName) {
            $attrs['data-confirm-type-name'] = $typeName;
        }

        $out = [];
        foreach ($attrs as $name => $value) {
            $out[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return implode(' ', $out);
    }
}
