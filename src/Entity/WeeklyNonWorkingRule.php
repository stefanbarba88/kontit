<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\WeeklyNonWorkingRuleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Dani u nedelji koji su neradni za celu firmu (SPEC 8k). Postoji jedan red; podrazumevano subota i nedelja.
 */
#[ORM\Entity(repositoryClass: WeeklyNonWorkingRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WeeklyNonWorkingRule
{
    use TimestampableTrait;

    public const DEFAULT_DAYS = [6, 7];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var list<int> ISO dani (1 = ponedeljak ... 7 = nedelja) */
    #[ORM\Column]
    private array $days = self::DEFAULT_DAYS;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return list<int>
     */
    public function getDays(): array
    {
        return $this->days;
    }

    /**
     * @param iterable<int|string> $days
     */
    public function setDays(iterable $days): static
    {
        $clean = [];
        foreach ($days as $day) {
            $day = (int) $day;
            if ($day >= 1 && $day <= 7) {
                $clean[$day] = $day;
            }
        }
        ksort($clean);
        $this->days = array_values($clean);

        return $this;
    }
}
