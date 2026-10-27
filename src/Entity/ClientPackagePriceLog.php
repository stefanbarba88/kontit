<?php

namespace App\Entity;

use App\Repository\ClientPackagePriceLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Istorija dogovorene cene paketa kod klijenta (stara cena, nova cena, od kada, ko je promenio).
 */
#[ORM\Entity(repositoryClass: ClientPackagePriceLogRepository::class)]
class ClientPackagePriceLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ClientPackage $clientPackage;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $oldPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $newPrice;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\ManyToOne]
    private ?User $changedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(ClientPackage $clientPackage, ?string $oldPrice, string $newPrice, \DateTimeImmutable $effectiveFrom, ?User $changedBy)
    {
        $this->clientPackage = $clientPackage;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->effectiveFrom = $effectiveFrom;
        $this->changedBy = $changedBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientPackage(): ClientPackage
    {
        return $this->clientPackage;
    }

    public function getOldPrice(): ?string
    {
        return $this->oldPrice;
    }

    public function getNewPrice(): string
    {
        return $this->newPrice;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
