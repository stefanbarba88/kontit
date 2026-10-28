<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\UserAbsenceRepository;
use App\Service\LeaveService;
use App\Service\TaskService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * pending_absence_count(): broj zahteva za odsustvo koje ulogovani šef ili admin treba da odobri (brojač u meniju, SPEC 8j.3).
 * leave_balance(user, godina) i absences_of(user, godina): kartica "Odsustva" na profilu i stranici korisnika.
 */
class AbsenceExtension extends AbstractExtension
{
    private ?int $count = null;

    public function __construct(
        private readonly Security $security,
        private readonly UserAbsenceRepository $absences,
        private readonly TaskService $tasks,
        private readonly LeaveService $leave,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_absence_count', $this->pendingCount(...)),
            new TwigFunction('leave_balance', fn (User $user, ?int $year = null) => $this->leave->balance($user, $year ?? (int) date('Y'))),
            new TwigFunction('absences_of', fn (User $user, ?int $year = null) => $this->absences->findInPeriod([$user], new \DateTimeImmutable((($year ?? (int) date('Y'))).'-01-01'), new \DateTimeImmutable((($year ?? (int) date('Y'))).'-12-31'))),
        ];
    }

    public function pendingCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->tasks->isLeader($user)) {
            return 0;
        }

        return $this->count ??= $this->absences->countPendingFor($user, $this->tasks->isAdmin($user));
    }
}
