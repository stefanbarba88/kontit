<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\JobCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Kategorija poslova (SPEC 8g.1). Kategorija koja ima poslove se ne briše, nego deaktivira.
 */
#[ORM\Entity(repositoryClass: JobCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Kategorija sa tim nazivom već postoji.')]
class JobCategory
{
    use TimestampableTrait;

    /** Ikonice koje se nude (moraju biti lokalno u assets/icons/lucide) */
    public const ICONS = ['book-open', 'receipt', 'wallet', 'file-text', 'file-check', 'calculator', 'landmark', 'users', 'user-plus', 'message-circle', 'briefcase', 'folder', 'clipboard-list', 'calendar', 'shield-check', 'tag', 'circle-ellipsis'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank(message: 'Unesi naziv kategorije.')]
    #[Assert\Length(max: 80)]
    private ?string $name = null;

    /** Jedna od 8 boja iz DESIGN.md 3.6 */
    #[ORM\Column(length: 7)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Izaberi boju.')]
    private string $color = '#64748B';

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Choice(choices: self::ICONS, message: 'Izaberi ikonicu sa liste.')]
    private ?string $icon = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(?string $name = null, string $color = '#64748B', ?string $icon = null, int $position = 0)
    {
        $this->name = $name;
        $this->color = $color;
        $this->icon = $icon;
        $this->position = $position;
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
