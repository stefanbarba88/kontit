<?php

namespace App\Service;

use App\Entity\ChecklistItem;
use App\Entity\ClientJob;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ClientJobRepository;
use App\Repository\TaskRepository;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pravi zadatke iz ugovorenih poslova klijenata (SPEC 8i.1). Zadatak nastaje leadDays dana pre roka (ScheduleCalculator);
 * jedinstveno (client_job_id, period_key) sprečava duplikate, pa je komanda bezbedna za ponovno pokretanje.
 * Raspored "Po potrebi" ne pravi zadatke.
 */
class TaskGenerationService
{
    /** Rok koji je prošao pre najviše ovoliko dana se još pravi (npr. cron nije radio nekoliko dana), stariji ne */
    public const CATCH_UP_DAYS = 31;

    public function __construct(
        private readonly ClientJobRepository $jobs,
        private readonly TaskRepository $tasks,
        private readonly UserRepository $users,
        private readonly UserAbsenceRepository $absences,
        private readonly ScheduleCalculator $schedule,
        private readonly TaskService $taskService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Zadaci koji nastaju do $horizonDays unapred (za pregled) i oni čije je vreme došlo, a nisu napravljeni.
     *
     * @return list<array{job: ClientJob, key: string, label: string, due: \DateTimeImmutable, shifted: bool, generateOn: \DateTimeImmutable, exists: bool, assignee: ?User, takenOverFrom: ?User}>
     */
    public function preview(int $horizonDays = 30, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $jobs = array_values(array_filter($this->jobs->findGeneratable(), static fn (ClientJob $j) => $j->getSchedule()->isAutomatic()));
        $existing = $this->tasks->findPeriodKeys($jobs);

        $rows = [];
        foreach ($jobs as $job) {
            $lead = $job->getSchedule()->getLeadDays() ?? 0;
            $from = $today->modify('-'.self::CATCH_UP_DAYS.' days');
            $to = $today->modify('+'.($horizonDays + $lead).' days');
            foreach ($this->schedule->forClientJob($job, $from, $to) as $occ) {
                $generateOn = $occ['due']->modify('-'.$lead.' days');
                if ($generateOn > $today->modify('+'.$horizonDays.' days') || !$job->isGeneratable($occ['due'])) {
                    continue;
                }
                [$assignee, $takenOverFrom] = $this->assignee($job, $today);
                $rows[] = [
                    'job' => $job,
                    'key' => $occ['key'],
                    'label' => $occ['label'],
                    'due' => $occ['due'],
                    'shifted' => $occ['shifted'],
                    'generateOn' => $generateOn,
                    'exists' => isset($existing[$job->getId()][$occ['key']]),
                    'assignee' => $assignee,
                    'takenOverFrom' => $takenOverFrom,
                ];
            }
        }
        usort($rows, static fn (array $a, array $b) => [$a['due'], $a['job']->getId()] <=> [$b['due'], $b['job']->getId()]);

        return $rows;
    }

    /**
     * Pravi zadatke čije je vreme došlo (danas >= rok - leadDays), a za taj period još ne postoje.
     *
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function generate(User $actor, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $result = ['created' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($this->preview(0, $today) as $row) {
            if ($row['exists']) {
                ++$result['skipped'];
                continue;
            }
            try {
                $this->createTask($row['job'], $row['key'], $row['label'], $row['due'], $today, $actor);
                ++$result['created'];
            } catch (\DomainException $e) {
                $result['errors'][] = sprintf('%s — %s: %s', $row['job']->getClient()->getShortName(), $row['job']->getJobTemplate()->getName(), $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Broj zadataka koji bi nastali da se generisanje pokrene sada (za SweetAlert pre "Generiši sada").
     */
    public function countDue(?\DateTimeImmutable $today = null): int
    {
        return \count(array_filter($this->preview(0, $today), static fn (array $r) => !$r['exists']));
    }

    /**
     * Zaduženi: override sa posla, inače glavni radnik (pa zamena ako glavnog nema).
     * Ako je izabrani tog dana na odobrenom odsustvu, zadatak dobija zamena klijenta i označava se kao preuzet.
     *
     * @return array{0: ?User, 1: ?User} [zaduženi, od koga je preuzet]
     */
    public function assignee(ClientJob $job, \DateTimeImmutable $on): array
    {
        $client = $job->getClient();
        $chosen = $job->getAssigneeOverride() ?? $client->getPrimaryAccountant();
        if (null === $chosen || !$chosen->isActive()) {
            $chosen = $client->getBackupAccountant();
        }
        if (null === $chosen || !$chosen->isActive()) {
            return [null, null];
        }

        $backup = $client->getBackupAccountant();
        if (null !== $this->absences->findApprovedCovering($chosen, $on)
            && null !== $backup && $backup !== $chosen && $backup->isActive()
            && null === $this->absences->findApprovedCovering($backup, $on)) {
            return [$backup, $chosen];
        }

        return [$chosen, null];
    }

    private function createTask(ClientJob $job, string $key, string $label, \DateTimeImmutable $due, \DateTimeImmutable $today, User $actor): Task
    {
        $template = $job->getJobTemplate();
        $client = $job->getClient();

        [$assignee, $takenOverFrom] = $this->assignee($job, $today);
        if (null === $assignee) {
            throw new \DomainException('nema aktivnog zaduženog (dodeli glavnog radnika klijentu).');
        }

        $task = (new Task())
            ->setTitle(sprintf('%s — %s — %s', $template->getName(), $client->getShortName(), $label))
            ->setDescription($template->getDescription())
            ->setClient($client)
            ->setDueDate($due)
            ->setPriority($template->getDefaultPriority())
            ->setCompletionMode($template->getCompletionMode())
            ->markGenerated($job, $key, $job->getEstimatedMinutes())
            ->setTakenOverFrom($takenOverFrom);

        foreach ($template->getChecklistItems() as $item) {
            $task->addChecklistItem(new ChecklistItem($task, (string) $item->getText(), $item->getPosition()));
        }

        $this->taskService->create($task, [$assignee], $actor, false, true);
        $this->em->flush();

        return $task;
    }

    /**
     * Korisnik u ime koga komanda (cron) pravi zadatke: prvi aktivni admin.
     */
    public function systemActor(): ?User
    {
        return $this->users->findActiveAdmins()[0] ?? null;
    }
}
