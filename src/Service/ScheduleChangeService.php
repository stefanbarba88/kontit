<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\ClientJob;
use App\Entity\JobTemplate;
use App\Entity\Schedule;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Repository\ClientJobRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Promena rasporeda i šta ona radi već generisanim zadacima (SPEC 8g.4).
 *
 *  - Preračunava se samo rok otvorenih (NOVO) zadataka čiji rok nije ručno promenjen, i to za isti period (period se ne preskače i ne duplira;
 *    jedinstvenost (client_job_id, period_key) ostaje jer se ključ ne menja).
 *  - Zadatak čiji period više ne postoji u novom rasporedu (promena ključa, npr. mesečno -> kvartalno) ostaje na starom roku i vraća se u `stranded`,
 *    da ga korisnik sam otkaže ili završi. Generator posle promene računa nove periode po novom ključu.
 *  - Zadaci u radu, na proveri i završeni se ne diraju.
 * Pregled (`jobImpact`, `templateImpact`) ništa ne menja, pa se može pokazati pre potvrde.
 */
class ScheduleChangeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository $tasks,
        private readonly ClientJobRepository $jobs,
        private readonly ScheduleResolver $resolver,
        private readonly ScheduleCalculator $calculator,
        private readonly ActivityLogger $logger,
    ) {
    }

    /**
     * Šta bi se desilo zadacima posla klijenta kad bi mu važio $effective raspored.
     */
    public function jobImpact(ClientJob $job, Schedule $effective): ScheduleImpact
    {
        return $this->impact([$job], [$job->getId() => $effective]);
    }

    /**
     * Šta bi se desilo zadacima klijenata koji nasleđuju raspored posla (prilagođeni se ne diraju).
     * Poziva se i kad je šablon već izmenjen u memoriji (forma), jer se rok zadataka poredi sa njihovim sačuvanim rokom.
     */
    public function templateImpact(JobTemplate $template): ScheduleImpact
    {
        $jobs = $this->inheritingJobs($template);

        return $this->impact($jobs, array_combine(array_map(static fn (ClientJob $j) => $j->getId(), $jobs), array_fill(0, \count($jobs), $template->getSchedule())));
    }

    /**
     * Postavlja prilagođen raspored za klijenta ($override prazan = vraća na podrazumevani) i preračunava otvorene zadatke.
     */
    public function changeJob(ClientJob $job, Schedule $override, ?User $actor): ScheduleImpact
    {
        $old = $this->resolver->forClientJob($job);
        $oldText = $this->text($job, $old);
        $wasCustom = $this->resolver->isCustomized($job);

        $job->setScheduleOverride($override->isEmpty() ? new Schedule() : $override);
        $effective = $this->resolver->forClientJob($job);
        $impact = $this->jobImpact($job, $effective);
        $this->apply($impact);

        if ($oldText !== ($newText = $this->text($job, $effective)) || $wasCustom !== $this->resolver->isCustomized($job)) {
            $this->log($job, $actor, $oldText, $newText, $impact);
        }
        $this->em->flush();

        return $impact;
    }

    /**
     * Posle čuvanja šablona: preračunava zadatke klijenata koji nisu prilagođeni. $before je sačuvan raspored pre izmene.
     */
    public function templateChanged(JobTemplate $template, Schedule $before, ?User $actor): ScheduleImpact
    {
        $total = new ScheduleImpact();
        if ($before->toArray() === $template->getSchedule()->toArray()) {
            return $total;
        }

        $oldText = 'Podrazumevano: '.$this->calculator->describe($before, true);
        $newText = 'Podrazumevano: '.$this->calculator->describe($template->getSchedule(), true);
        foreach ($this->inheritingJobs($template) as $job) {
            $impact = $this->jobImpact($job, $template->getSchedule());
            $this->apply($impact);
            $this->log($job, $actor, $oldText, $newText, $impact);
            $total->merge($impact);
        }
        $this->em->flush();

        return $total;
    }

    /**
     * Sačuvan (pre izmene u formi) raspored entiteta, iz Doctrine-ovih izvornih podataka; prazan raspored za nov entitet.
     */
    public function persistedSchedule(JobTemplate|ClientJob $entity): Schedule
    {
        $property = $entity instanceof JobTemplate ? 'schedule' : 'scheduleOverride';
        $data = $this->em->getUnitOfWork()->getOriginalEntityData($entity);
        $values = [];
        foreach (array_keys(Schedule::fromArray([])->toArray()) as $field) {
            $values[$field] = $data[$property.'.'.$field] ?? null;
        }
        // enum kolone Doctrine u izvornim podacima drži kao enum ili kao sirov tekst
        foreach (['type', 'nonWorkingDayShift'] as $field) {
            if ($values[$field] instanceof \BackedEnum) {
                $values[$field] = $values[$field]->value;
            }
        }

        return Schedule::fromArray($values);
    }

    /**
     * @return list<ClientJob>
     */
    private function inheritingJobs(JobTemplate $template): array
    {
        if (null === $template->getId()) {
            return [];
        }

        return array_values(array_filter(
            $this->jobs->findBy(['jobTemplate' => $template]),
            fn (ClientJob $j) => !$this->resolver->isCustomized($j),
        ));
    }

    /**
     * @param list<ClientJob>         $jobs
     * @param array<int, Schedule>    $schedules id posla => raspored koji bi važio
     */
    private function impact(array $jobs, array $schedules): ScheduleImpact
    {
        $impact = new ScheduleImpact();
        foreach ($this->tasks->findRecalculable($jobs) as $task) {
            $schedule = $schedules[$task->getClientJob()->getId()];
            $due = $this->resolver->scheduledDue($task, $schedule);
            if (null === $due) {
                $impact->stranded[] = $task;
            } elseif ($due != $task->getDueDate()) {
                $impact->moves[] = ['task' => $task, 'from' => $task->getDueDate(), 'to' => $due];
            }
        }

        return $impact;
    }

    private function apply(ScheduleImpact $impact): void
    {
        foreach ($impact->moves as $move) {
            $move['task']->setDueDate($move['to']);
        }
    }

    private function text(ClientJob $job, Schedule $schedule): string
    {
        return ($this->resolver->isCustomized($job) ? 'Prilagođeno: ' : 'Podrazumevano: ').$this->calculator->describe($schedule, true);
    }

    private function log(ClientJob $job, ?User $actor, string $old, string $new, ScheduleImpact $impact): void
    {
        $client = $job->getClient();
        if (null === $client || null === $client->getId()) {
            return;
        }
        $this->em->persist(new ActivityLog(
            ActivityLog::CLIENT,
            $client->getId(),
            $client->getShortName(),
            $actor,
            ActivityAction::UPDATED,
            $this->logger->scheduleChange((string) $job->getJobTemplate()->getName(), $old, $new, $impact->movedCount(), \count($impact->stranded)),
        ));
    }
}
