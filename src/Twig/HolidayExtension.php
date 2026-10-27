<?php

namespace App\Twig;

use App\Repository\HolidayRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * holiday_dates(): datumi praznika (Y-m-d) za obeležavanje u kalendaru (flatpickr, DESIGN.md 17).
 */
class HolidayExtension extends AbstractExtension
{
    /** @var list<string>|null */
    private ?array $dates = null;

    public function __construct(private readonly HolidayRepository $holidays)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('holiday_dates', $this->dates(...))];
    }

    /**
     * @return list<string>
     */
    public function dates(): array
    {
        if (null === $this->dates) {
            $this->dates = [];
            foreach ($this->holidays->findBy([], ['date' => 'ASC']) as $holiday) {
                $this->dates[] = $holiday->getDate()->format('Y-m-d');
            }
        }

        return $this->dates;
    }
}
