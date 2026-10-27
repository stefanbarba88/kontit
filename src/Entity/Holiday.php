<?php

namespace App\Entity;

use App\Repository\HolidayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Neradni dan (praznik). Rok zadatka koji padne na vikend ili praznik pomera se na prvi sledeći radni dan.
 */
#[ORM\Entity(repositoryClass: HolidayRepository::class)]
#[UniqueEntity(fields: ['date'], message: 'Taj datum je već unet.')]
class Holiday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    #[Assert\NotNull(message: 'Unesi datum.')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Unesi naziv.')]
    #[Assert\Length(max: 150)]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
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
}
