<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\UserAbsence;
use App\Enum\NotificationType;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Automatsko odobravanje posle roka (SPEC 8m), pokreće ga app:approvals:auto-approve na 15 minuta.
 *  - zahtev za odsustvo na odobrenju čiji je rok istekao postaje AUTOMATSKI_ODOBRENO (istorija: "Sistem"),
 *    a podnosilac i odobravaoci dobijaju obaveštenje; 24 h pre isteka odobravalac dobija podsetnik;
 *  - potvrda zadatka (NA_PROVERI) samo ako je uključena u Podešavanjima, posle N radnih dana od predaje.
 */
class ApprovalService
{
    public function __construct(
        private readonly UserAbsenceRepository $absences,
        private readonly TaskRepository $tasks,
        private readonly UserRepository $users,
        private readonly AbsenceService $absenceService,
        private readonly TaskCompletionService $completion,
        private readonly NotificationService $notifications,
        private readonly ApprovalDeadlineCalculator $deadlines,
        private readonly SettingsService $settings,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{approved: int, reminded: int, tasks: int}
     */
    public function run(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return [
            'reminded' => $this->remind($now),
            'approved' => $this->autoApproveAbsences($now),
            'tasks' => $this->autoApproveTasks($now),
        ];
    }

    public function autoApproveAbsences(\DateTimeImmutable $now): int
    {
        $count = 0;
        foreach ($this->absences->findPendingWithDeadlineBefore($now) as $absence) {
            $absence->approveAutomatically();
            $this->em->flush();
            $this->absenceService->activate($absence, null);

            $this->notifications->notify($absence->getUser(), NotificationType::ABSENCE_AUTO_APPROVED, 'Odsustvo je automatski odobreno', sprintf('Odobravalac nije odgovorio do roka, pa je tvoje odsustvo automatski odobreno: %s.', $absence->summary()), ['absence_index']);
            $this->notifications->notifyMany($this->absenceService->approversFor($absence->getUser()), NotificationType::ABSENCE_AUTO_APPROVED, 'Odsustvo je automatski odobreno', sprintf('Rok je istekao, pa je odsustvo za %s automatski odobreno: %s. Admin može da poništi odobrenje.', $absence->getUser()->getFullName(), $absence->summary()), ['absence_index']);
            ++$count;
        }

        return $count;
    }

    /**
     * Podsetnik odobravaocima 24 h pre isteka roka (jednom po zahtevu).
     */
    public function remind(\DateTimeImmutable $now): int
    {
        $count = 0;
        foreach ($this->absences->findPendingWithDeadlineBefore($now->modify('+24 hours')) as $absence) {
            if ($absence->isReminderSent() || $absence->getApprovalDeadline() <= $now) {
                continue;
            }
            $this->notifications->notifyMany($this->absenceService->approversFor($absence->getUser()), NotificationType::APPROVAL_DEADLINE_SOON, 'Ističe rok za odobrenje', sprintf('Odsustvo za %s (%s) se automatski odobrava %s ako ne odgovoriš.', $absence->getUser()->getFullName(), $absence->summary(), $absence->getApprovalDeadline()->format('d.m.Y. \u H:i')), ['absence_index', ['tab' => 'odobrenje']]);
            $absence->setReminderSent(true);
            $this->em->flush();
            ++$count;
        }

        return $count;
    }

    /**
     * Potvrda zadataka na proveri posle N radnih dana od predaje, samo kad je uključena u Podešavanjima.
     */
    public function autoApproveTasks(\DateTimeImmutable $now): int
    {
        if ('1' !== $this->settings->get(SettingsService::TASK_AUTO_APPROVE)) {
            return 0;
        }
        $actor = $this->users->findActiveAdmins()[0] ?? null;
        if (null === $actor) {
            return 0;
        }
        $hours = 24 * (int) $this->settings->getFloat(SettingsService::TASK_AUTO_APPROVE_DAYS);
        $count = 0;
        /** @var Task $task */
        foreach ($this->tasks->findBy(['status' => TaskStatus::NA_PROVERI]) as $task) {
            // posebne potvrde se nikad ne odobravaju automatski
            if ($task->isSpecialReview()) {
                continue;
            }
            $submitted = $task->getSubmittedAt();
            if (null === $submitted || $this->deadlines->deadline($submitted, $hours) > $now) {
                continue;
            }
            try {
                $this->completion->approve($task, $actor);
                ++$count;
            } catch (\DomainException) {
                // zadatak se u međuvremenu promenio
            }
        }

        return $count;
    }

    /**
     * Admin poništava automatsko odobrenje (razlog obavezan): zahtev postaje odbijen, a podnosilac dobija obaveštenje.
     */
    public function revoke(UserAbsence $absence, \App\Entity\User $admin, string $reason): void
    {
        $this->absenceService->revokeAutoApproval($absence, $admin, $reason);
    }
}
