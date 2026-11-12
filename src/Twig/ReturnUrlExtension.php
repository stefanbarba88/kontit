<?php

namespace App\Twig;

use App\Service\ReturnUrlResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * return_to_path(): trenutna adresa (sa filterima i stranom) za parametar returnTo u linkovima ka formama,
 *   npr. path('task_new', {returnTo: return_to_path()}); null (parametar se izostavlja) ako adresa nije bezbedna.
 * return_to_value(raw): očišćena vrednost za skriveno polje i dugme "Otkaži".
 */
class ReturnUrlExtension extends AbstractExtension
{
    public function __construct(private readonly ReturnUrlResolver $returnUrl)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('return_to_path', $this->returnUrl->current(...)),
            new TwigFunction('return_to_value', $this->returnUrl->sanitize(...)),
        ];
    }
}
