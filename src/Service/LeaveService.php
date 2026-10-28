<?php

namespace App\Service;

use App\Entity\LeaveBalance;
use App\Entity\User;
use App\Repository\LeaveBalanceRepository;
use App\Repository\UserAbsenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Godišnji odmor (SPEC 8j.3): pravo, preneti dani (važe do 30. juna) i iskorišćeni dani iz odobrenih odsustava koja troše godišnji.
 * Godišnji odmor prvo troši prenete dane; preneti dani koji nisu iskorišćeni do 30. juna propadaju.
 * Prenos se ne računa sam (bez istorije bi svakome dodao celu prošlu godinu): admin ga upisuje, a sistem predlaže koliko je ostalo.
 */
class LeaveService
{
    public function __construct(
        private readonly LeaveBalanceRepository $balances,
        private readonly UserAbsenceRepository $absences,
        private readonly SettingsService $settings,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function entitled(User $user, int $year): float
    {
        return $this->balances->findFor($user, $year)?->getEntitled()
            ?? (float) ($user->getAnnualLeaveDays() ?? $this->settings->getFloat(SettingsService::ANNUAL_LEAVE_DAYS));
    }

    /**
     * @return array{year: int, entitled: float, carriedOver: float, carryDeadline: \DateTimeImmutable, carryUsed: float, carryExpired: float, used: float, pending: float, remaining: float, suggestedCarry: float}
     */
    public function balance(User $user, int $year, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $row = $this->balances->findFor($user, $year);
        $entitled = $this->entitled($user, $year);
        $carried = $row?->getCarriedOver() ?? 0.0;
        $deadline = new \DateTimeImmutable($year.'-06-30');

        $used = $this->absences->sumLeaveDays($user, $year);
        $usedByDeadline = $this->absences->sumLeaveDays($user, $year, $deadline);
        $carryUsed = min($carried, $usedByDeadline);
        $carryExpired = $today > $deadline ? $carried - $carryUsed : 0.0;

        return [
            'year' => $year,
            'entitled' => $entitled,
            'carriedOver' => $carried,
            'carryDeadline' => $deadline,
            'carryUsed' => $carryUsed,
            'carryExpired' => $carryExpired,
            'used' => $used,
            'pending' => $this->absences->sumLeaveDays($user, $year, null, true),
            'remaining' => $entitled + $carried - $used - $carryExpired,
            // predlog prenosa u sledeću godinu: neiskorišćeno od prava ove godine
            'suggestedCarry' => max(0.0, $entitled - max(0.0, $used - $carryUsed)),
        ];
    }

    /**
     * Admin ručno podešava pravo i prenos za godinu.
     */
    public function set(User $user, int $year, float $entitled, float $carriedOver): void
    {
        $row = $this->balances->findFor($user, $year);
        if (null === $row) {
            $this->em->persist(new LeaveBalance($user, $year, $entitled, $carriedOver));
        } else {
            $row->setEntitled($entitled)->setCarriedOver($carriedOver);
        }
        $this->em->flush();
    }
}
