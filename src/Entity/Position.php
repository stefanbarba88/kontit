<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\Role;
use App\Repository\PositionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pozicija zaposlenog (nivo, npr. Junior, Samostalni knjigovođa). NE daje prava pristupa: prava određuju samo ROLE_* ("Prava pristupa").
 * suggestedAccessRole je samo predlog u formi korisnika, ništa se ne dodeljuje automatski.
 * Pozicija koja je ikad korišćena (korisnik ili istorija) se ne briše, nego deaktivira.
 */
#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Pozicija sa tim nazivom već postoji.')]
class Position
{
    use TimestampableTrait;

    /** Osam boja iz DESIGN.md 3.6 */
    public const COLORS = ['#0089FF', '#5B6CFF', '#8B5CF6', '#14B8A6', '#22C55E', '#F59E0B', '#F43F5E', '#64748B'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Unesi naziv pozicije.')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 7)]
    #[Assert\Choice(choices: self::COLORS, message: 'Izaberi boju sa liste.')]
    private string $color = '#64748B';

    /** Redosled po senioritetu (manji = niže), menja se prevlačenjem u listi */
    #[ORM\Column(name: 'level', options: ['default' => 0])]
    private int $level = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** Mesečni kapacitet u satima za analitiku opterećenja; važi ako korisnik nema svoj (korisnik > pozicija > globalno) */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 400)]
    private ?int $monthlyCapacityHours = null;

    #[ORM\Column(length: 20, nullable: true, enumType: Role::class)]
    private ?Role $suggestedAccessRole = null;

    /** Zadaci radnika na ovoj poziciji idu na proveru (šef tima ili admin), vidi TaskReviewPolicy */
    #[ORM\Column(options: ['default' => false])]
    private bool $requiresTaskReview = false;

    public function __construct(?string $name = null, string $color = '#64748B', int $level = 0, ?Role $suggestedAccessRole = null)
    {
        $this->name = $name;
        $this->color = $color;
        $this->level = $level;
        $this->suggestedAccessRole = $suggestedAccessRole;
    }

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
        $this->name = null === $name ? null : trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = null === $description ? null : trim($description);
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = strtoupper($color);

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

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

    public function getMonthlyCapacityHours(): ?int
    {
        return $this->monthlyCapacityHours;
    }

    public function setMonthlyCapacityHours(?int $hours): static
    {
        $this->monthlyCapacityHours = $hours;

        return $this;
    }

    public function requiresTaskReview(): bool
    {
        return $this->requiresTaskReview;
    }

    public function setRequiresTaskReview(bool $value): static
    {
        $this->requiresTaskReview = $value;

        return $this;
    }

    public function getSuggestedAccessRole(): ?Role
    {
        return $this->suggestedAccessRole;
    }

    /** Samo RADNIK, SEF i ADMIN se nude kao predlog */
    public function setSuggestedAccessRole(?Role $role): static
    {
        $this->suggestedAccessRole = null !== $role && Role::SUPERADMIN !== $role ? $role : null;

        return $this;
    }
}
