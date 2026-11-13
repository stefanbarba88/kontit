<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\ReviewMode;
use App\Enum\TaskStatus;
use App\Repository\NotificationRepository;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Connection;

/**
 * Posebne potvrde: koliko zadatak čeka, dnevno upozorenje proveravačima i uticaj deaktivacije/promene prava proveravača.
 */
class SpecialApprovalService
{
    /** Posle koliko radnih dana čekanja posebna potvrda postaje crvena i šalje se upozorenje */
    public const REMIND_AFTER_WORKING_DAYS = 2;

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly TaskReviewPolicy $policy,
        private readonly NotificationService $notifications,
        private readonly NotificationRepository $notificationRepository,
        private readonly WorkingDayService $workingDays,
        private readonly Connection $db,
    ) {
    }

    /**
     * Radni dani koje zadatak čeka na potvrdu (dani posle slanja, do danas, samo radni).
     */
    public function waitingWorkingDays(Task $task, ?\DateTimeImmutable $now = null): int
    {
        $submitted = $task->getSubmittedAt();
        if (null === $submitted) {
            return 0;
        }
        $now ??= new \DateTimeImmutable();
        $from = $submitted->setTime(0, 0)->modify('+1 day');
        $to = $now->setTime(0, 0);

        return $from > $to ? 0 : $this->workingDays->countWorkingDays($from, $to);
    }

    /** Radni dani od datuma slanja do danas (isto brojanje kao waitingWorkingDays) */
    public function waitingWorkingDaysSince(\DateTimeImmutable $since, ?\DateTimeImmutable $now = null): int
    {
        $from = $since->setTime(0, 0)->modify('+1 day');
        $to = ($now ?? new \DateTimeImmutable())->setTime(0, 0);

        return $from > $to ? 0 : $this->workingDays->countWorkingDays($from, $to);
    }

    /** Čeka duže od dozvoljenog (crveno u listi, uslov za upozorenje) */
    public function isOverdue(Task $task, ?\DateTimeImmutable $now = null): bool
    {
        return $this->waitingWorkingDays($task, $now) > self::REMIND_AFTER_WORKING_DAYS;
    }

    /**
     * Upozorenje jednom dnevno svim proveravačima zadataka koji posebnu potvrdu čekaju duže od 2 radna dana.
     *
     * @return int broj poslatih obaveštenja
     */
    public function remind(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $today = $now->setTime(0, 0);
        $sent = 0;
        /** @var Task $task */
        foreach ($this->tasks->findBy(['reviewMode' => ReviewMode::SPECIAL, 'status' => TaskStatus::NA_PROVERI]) as $task) {
            if (!$this->isOverdue($task, $now)) {
                continue;
            }
            $route = ['task_show', ['id' => $task->getId()]];
            $link = $this->notifications->path($route);
            foreach ($this->policy->reviewersFor($task) as $reviewer) {
                if ($this->notificationRepository->existsSince($reviewer, NotificationType::SPECIAL_APPROVAL_REMINDER, $link, $today)) {
                    continue;
                }
                $this->notifications->notify(
                    $reviewer,
                    NotificationType::SPECIAL_APPROVAL_REMINDER,
                    'Posebna potvrda čeka duže od 2 radna dana',
                    sprintf('Zadatak „%s“ čeka tvoju posebnu potvrdu već %d radna dana.', $task->getTitle(), $this->waitingWorkingDays($task, $now)),
                    $route,
                );
                ++$sent;
            }
        }

        return $sent;
    }

    /**
     * Koliko je poslova sa posebnom potvrdom i otvorenih zadataka na kojima je $user JEDINI važeći proveravač.
     * Poziva se pre deaktivacije ili spuštanja prava, da admin dobije upozorenje (tada potvrđuje superadmin).
     *
     * @return array{jobs: int, tasks: int}
     */
    public function soleApproverImpact(User $user): array
    {
        $others = "NOT EXISTS (SELECT 1 FROM %1\$s b JOIN app_user bu ON bu.id = b.user_id WHERE b.%2\$s = x.id AND b.user_id <> :u AND bu.is_active = 1 AND (bu.roles LIKE '%%ROLE_ADMIN%%' OR bu.roles LIKE '%%ROLE_SUPERADMIN%%'))";
        $jobs = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM job_template x JOIN job_template_approver a ON a.job_template_id = x.id AND a.user_id = :u WHERE x.requires_approval = 1 AND x.is_active = 1 AND ".sprintf($others, 'job_template_approver', 'job_template_id'),
            ['u' => $user->getId()],
        );
        $tasks = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM task x JOIN task_required_approver a ON a.task_id = x.id AND a.user_id = :u WHERE x.review_mode = 'SPECIAL' AND x.status IN ('NOVO','U_RADU','CEKA_KLIJENTA','NA_PROVERI') AND ".sprintf($others, 'task_required_approver', 'task_id'),
            ['u' => $user->getId()],
        );

        return ['jobs' => $jobs, 'tasks' => $tasks];
    }

    /** Tekst upozorenja ili null kad korisnik nije ničiji jedini proveravač */
    public function soleApproverWarning(User $user): ?string
    {
        ['jobs' => $jobs, 'tasks' => $tasks] = $this->soleApproverImpact($user);
        if (0 === $jobs && 0 === $tasks) {
            return null;
        }

        return sprintf('%s je jedini proveravač za %d posebnih poslova i %d otvorenih zadataka. Do izbora novog proveravača te zadatke potvrđuje superadmin.', $user->getFullName(), $jobs, $tasks);
    }
}
