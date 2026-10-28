<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\DocumentCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Kategorija dokumenta korisnika (SPEC 8l): Ugovor, Rešenje, Potvrda, Izvod, Doznaka, Ostalo. Ne briše se, nego deaktivira.
 */
#[ORM\Entity(repositoryClass: DocumentCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Kategorija sa tim nazivom već postoji.')]
class DocumentCategory
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank(message: 'Unesi naziv kategorije.')]
    #[Assert\Length(max: 80)]
    private ?string $name = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(?string $name = null, int $position = 0)
    {
        $this->name = $name;
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
