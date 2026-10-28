<?php

namespace App\Twig;

use App\Entity\Schedule;
use App\Service\ScheduleCalculator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * schedule_describe(schedule, short): opis rasporeda rečima (SPEC 8g.3), npr. "Mesečno, 15.".
 */
class ScheduleExtension extends AbstractExtension
{
    public function __construct(private readonly ScheduleCalculator $calculator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('schedule_describe', fn (?Schedule $schedule, bool $short = false): string => null === $schedule ? '' : $this->calculator->describe($schedule, $short)),
        ];
    }
}
