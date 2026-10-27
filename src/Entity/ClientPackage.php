<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\ClientPackageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paket vezan za klijenta, sa dogovorenom cenom. Ne briše se: uklanjanje postavlja endDate i isActive=false.
 */
#[ORM\Entity(repositoryClass: ClientPackageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ClientPackage
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Izaberi paket.')]
    private ?ServicePackage $package = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Unesi dogovorenu cenu.')]
    #[Assert\PositiveOrZero]
    private ?string $agreedPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $discountPercent = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Unesi početak.')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getPackage(): ?ServicePackage
    {
        return $this->package;
    }

    public function setPackage(?ServicePackage $package): static
    {
        $this->package = $package;

        return $this;
    }

    public function getAgreedPrice(): ?string
    {
        return $this->agreedPrice;
    }

    public function setAgreedPrice(?string $price): static
    {
        $this->agreedPrice = $price;

        return $this;
    }

    public function getDiscountPercent(): ?string
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?string $discount): static
    {
        $this->discountPercent = '' === $discount ? null : $discount;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $date): static
    {
        $this->startDate = $date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $date): static
    {
        $this->endDate = $date;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = '' === $note ? null : $note;

        return $this;
    }
}
