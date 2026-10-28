<?php

namespace App\Service;

use App\Entity\ChecklistItem;
use App\Entity\ClientJob;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ClientJobRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pravi zadatke iz poslova klijenata (SPEC 8i). Zadatak nastaje leadDays dana pre roka (ScheduleCalculator);
 * jedinstveni ključ "idPosla:period" sprečava duplikate pri ponovnom pokretanju.
 */
class TaskGenerationService
{
    public function __construct(
        private readonly ClientJobRepository $jobs,
        private readonly TaskRepository $tasks,
        private readonly UserRepository $users,
        private readonly ScheduleCalculator $schedule,
        private readonly TaskService $taskService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Zadaci koji bi nastali pri pokretanju na dati dan, sa rokom do $horizonDays unapred (za pregled).
     *
     * @return list<array{job: ClientJob, key: string, label: string, due: \DateTimeImmutable, generateOn: \DateTimeImmutable, exists: bool}>
     */
    public function preview(int $horizonDays = 30, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $rows = [];
        foreach ($this->jobs->findGeneratable() as $job) {
            if (!$job->getSchedule()->isAutomatic()) {
                continue; // "po potrebi": zadatak se pravi ručno
            }
            $lead = $job->getSchedule()->getLeadDays() ?? 0;
            $to = $today->modify('+'.($horizonDays + $lead).' days');
            foreach ($this->schedule->forClientJob($job, $today->modify('-'.$lead.' days'), $to) as $occ) {
                $generateOn = $occ['due']->modify('-'.$lead.' days');
                if ($generateOn > $today->modify('+'.$horizonDays.' days') || !$job->isGeneratable($occ['due'])) {
                    continue;
                }
                $rows[] = $occ + [
                    'job' => $job,
                    'generateOn' => $generateOn,
                    'exists' => null !== $this->tasks->findOneBy(['generationKey' => $job->getId().':'.$occ['key']]),
                ];
            }
        }
        usort($rows, static fn (array $a, array $b) => $a['due'] <=> $b['due']);

        return $rows;
    }

    /**
     * Generiše zadatke čije je vreme došlo (generateOn <= danas, rok još nije davno prošao).
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
                $this->createTask($row['job'], $row['key'], $row['label'], $row['due'], $actor);
                ++$result['created'];
            } catch (\DomainException $e) {
                $result['errors'][] = sprintf('%s — %s: %s', $row['job']->getClient()->getShortName(), $row['job']->getJobTemplate()->getName(), $e->getMessage());
            }
        }

        return $result;
    }

    private function createTask(ClientJob $job, string $key, string $label, \DateTimeImmutable $due, User $actor): Task
    {
        $template = $job->getJobTemplate();
        $client = $job->getClient();

        $assignee = $job->getAssigneeOverride() ?? $client->getPrimaryAccountant() ?? $client->getBackupAccountant();
        if (null === $assignee || !$assignee->isActive()) {
            throw new \DomainException('nema aktivnog izvršioca (dodelite glavnog radnika klijentu).');
        }

        $title = 'ONCE' === $key
            ? sprintf('%s — %s', $template->getName(), $client->getShortName())
            : sprintf('%s — %s — %s', $template->getName(), $client->getShortName(), $label);

        $task = (new Task())
            ->setTitle($title)
            ->setDescription($template->getDescription())
            ->setClient($client)
            ->setDueDate($due)
            ->setPriority($template->getDefaultPriority())
            ->setCompletionMode($template->getCompletionMode())
            ->markGenerated($job, $key, $job->getEstimatedMinutes());

        foreach ($template->getChecklistItems() as $item) {
            $task->addChecklistItem(new ChecklistItem($task, (string) $item->getText(), $item->getPosition()));
        }

        $this->taskService->create($task, [$assignee], $actor);
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
