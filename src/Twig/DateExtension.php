<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * day_name: naziv dana u nedelji na srpskom (latinica) preko IntlDateFormatter, npr. {{ task.dueDate|day_name }} → "četvrtak",
 * {{ d|day_name('short') }} → "čet". Jedino mesto za prikaz naziva dana, bez ručnih nizova i date('w') indeksa.
 */
class DateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('day_name', $this->dayName(...))];
    }

    public function dayName(\DateTimeInterface|string $date, string $style = 'long'): string
    {
        $date = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date);
        $pattern = 'short' === $style ? 'EEE' : 'EEEE';
        $formatter = new \IntlDateFormatter('sr_Latn_RS', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $date->getTimezone(), \IntlDateFormatter::GREGORIAN, $pattern);

        return mb_strtolower((string) $formatter->format($date));
    }
}
