<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\AbsenceTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Vrsta odsustva (SPEC 8j.1): da li traži odobrenje, da li troši godišnji odmor, da li traži dokument (doznaka).
 */
#[ORM\Entity(repositoryClass: AbsenceTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Vrsta sa tim nazivom već postoji.')]
class AbsenceType
{
    use TimestampableTrait;

    public const ICONS = ['palmtree', 'plane', 'heart-pulse', 'church', 'briefcase', 'wallet', 'circle-ellipsis', 'calendar-x'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank(message: 'Unesi naziv.')]
    #[Assert\Length(max: 80)]
    private ?string $name = null;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Izaberi boju.')]
    private string $color = '#0089FF';

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Choice(choices: self::ICONS, message: 'Izaberi ikonicu sa liste.')]
    private ?string $icon = null;

    /** Ne traži odobrenje = samo evidencija (npr. bolovanje), odmah je na snazi */
    #[ORM\Column]
    private bool $requiresApproval = true;

    #[ORM\Column]
    private bool $countsAgainstLeave = false;

    #[ORM\Column]
    private bool $requiresDocument = false;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $maxDaysPerYear = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isActive = true;

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = '' === $icon ? null : $icon;

        return $this;
    }

    public function isRequiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function setRequiresApproval(bool $requiresApproval): static
    {
        $this->requiresApproval = $requiresApproval;

        return $this;
    }

    public function isCountsAgainstLeave(): bool
    {
        return $this->countsAgainstLeave;
    }

    public function setCountsAgainstLeave(bool $countsAgainstLeave): static
    {
        $this->countsAgainstLeave = $countsAgainstLeave;

        return $this;
    }

    public function isRequiresDocument(): bool
    {
        return $this->requiresDocument;
    }

    public function setRequiresDocument(bool $requiresDocument): static
    {
        $this->requiresDocument = $requiresDocument;

        return $this;
    }

    public function getMaxDaysPerYear(): ?int
    {
        return $this->maxDaysPerYear;
    }

    public function setMaxDaysPerYear(?int $maxDaysPerYear): static
    {
        $this->maxDaysPerYear = $maxDaysPerYear;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $active): static
    {
        $this->isActive = $active;

        return $this;
    }
}
