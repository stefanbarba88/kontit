<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\LeaveBalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Godišnji odmor korisnika za godinu (SPEC 8j.3): pravo i preneti dani iz prethodne godine (važe do 30. juna).
 * Iskorišćeni dani se ne čuvaju, nego se računaju iz odobrenih odsustava koja troše godišnji (LeaveService).
 * Red postoji kad admin ručno podesi pravo ili prenos; bez njega važi User.annualLeaveDays i izračunat prenos.
 */
#[ORM\Entity(repositoryClass: LeaveBalanceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_LEAVE_USER_YEAR', columns: ['user_id', 'year'])]
class LeaveBalance
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $year;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1)]
    #[Assert\PositiveOrZero]
    private string $entitled = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1)]
    #[Assert\PositiveOrZero]
    private string $carriedOver = '0';

    public function __construct(User $user, int $year, float $entitled, float $carriedOver)
    {
        $this->user = $user;
        $this->year = $year;
        $this->setEntitled($entitled);
        $this->setCarriedOver($carriedOver);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getEntitled(): float
    {
        return (float) $this->entitled;
    }

    public function setEntitled(float $days): static
    {
        $this->entitled = number_format($days, 1, '.', '');

        return $this;
    }

    public function getCarriedOver(): float
    {
        return (float) $this->carriedOver;
    }

    public function setCarriedOver(float $days): static
    {
        $this->carriedOver = number_format($days, 1, '.', '');

        return $this;
    }
}
