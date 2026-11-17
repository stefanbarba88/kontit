<?php

namespace App\Service;

use App\Entity\Schedule;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ScheduleType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rok zadatka i raspored (SPEC 8g.5, nivo zadatka).
 *  - Promena roka samo za ovaj zadatak postavlja `dueDateManuallySet`, pa ga ni generator ni izmena rasporeda ne preračunavaju.
 *  - "Primeni i na buduće periode": iz novog roka se izvodi prilagođen raspored posla klijenta. Uzima se raspored koji sada važi
 *    (leadDays, nonWorkingDayShift i razmak ostaju), a menja se samo dan u mesecu (ili dan u nedelji). Ide kroz ScheduleChangeService,
 *    pa važe ista pravila preračuna otvorenih zadataka.
 *  - Zadatak koji nije nastao iz posla klijenta (ručno napravljen) nema šta da nasledi: njemu se rok samo postavlja.
 */
class TaskDueService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ScheduleResolver $resolver,
        private readonly ScheduleChangeService $changes,
        private readonly ScheduleCalculator $calculator,
        private readonly WorkingDayService $workingDays,
    ) {
    }

    /**
     * Da li zadatak ima podrazumevani rok koji se može preskočiti ručnom izmenom (nastao je iz perioda posla klijenta).
     */
    public function isScheduled(Task $task): bool
    {
        return null !== $task->getClientJob() && null !== $task->getPeriodKey();
    }

    /**
     * Raspored za buduće periode izveden iz novog roka, ili null ako se za tip rasporeda ne može izvesti
     * (dnevno, "po potrebi") ili zadatak nije vezan za posao klijenta.
     */
    public function deriveSchedule(Task $task, \DateTimeImmutable $newDue, bool $lastDay = false): ?Schedule
    {
        $job = $task->getClientJob();
        if (null === $job || !$this->isScheduled($task)) {
            return null;
        }
        $derived = clone $this->resolver->forClientJob($job);

        switch ($derived->getType()) {
            case ScheduleType::WEEKLY:
                $derived->setDayOfWeek((int) $newDue->format('N'));
                break;
            case ScheduleType::MONTHLY:
            case ScheduleType::QUARTERLY:
            case ScheduleType::YEARLY:
                $derived->setDayOfMonth($lastDay && $this->offersLastDay($newDue) ? Schedule::LAST_DAY : (int) $newDue->format('j'));
                break;
            case ScheduleType::NTH_WORKDAY:
                // redni broj radnog dana izabranog datuma; neradni dan nema redni broj, pa se za budućnost ne nudi
                if (!$this->workingDays->isWorkingDay($newDue)) {
                    return null;
                }
                $derived->setNthWorkingDay($this->workingDays->countWorkingDays($newDue->modify('first day of this month'), $newDue));
                break;
            case ScheduleType::WORKDAY_END:
                if (!$this->workingDays->isWorkingDay($newDue)) {
                    return null;
                }
                $derived->setWorkingDayFromEnd($this->workingDays->countWorkingDays($newDue, $newDue->modify('last day of this month')));
                break;
            default:
                return null;
        }

        return $derived;
    }

    /**
     * 29., 30., 31. ili poslednji dan meseca: nudi se "poslednji dan u mesecu", jer takav dan ne postoji u kraćim mesecima.
     */
    public function offersLastDay(\DateTimeImmutable $due): bool
    {
        return (int) $due->format('j') >= 29 || $due->format('j') === $due->format('t');
    }

    /**
     * Šta bi se desilo, bez izmena: izvedeni raspored rečima, upozorenje i uticaj na ostale otvorene zadatke posla.
     *
     * @return array{canFuture: bool, lastDayOffer: bool, future: ?string, warning: ?string, impact: ?array<string, mixed>, defaultDue: ?string}
     */
    public function preview(Task $task, \DateTimeImmutable $newDue, bool $lastDay): array
    {
        $derived = $this->deriveSchedule($task, $newDue, $lastDay);

        $out = [
            'canFuture' => null !== $derived,
            'lastDayOffer' => null !== $derived && ScheduleType::WEEKLY !== $derived->getType() && $this->offersLastDay($newDue),
            'future' => null,
            'warning' => null,
            'impact' => null,
            'defaultDue' => $this->resolver->scheduledDue($task)?->format('d.m.Y.'),
        ];
        if (null === $derived) {
            return $out;
        }

        $out['future'] = 'Od sledećeg perioda: '.$this->phrase($derived);
        if (Schedule::LAST_DAY !== $derived->getDayOfMonth() && (int) $derived->getDayOfMonth() >= 29) {
            $out['warning'] = sprintf('U mesecima koji nemaju %d. dan rok je poslednji dan meseca.', $derived->getDayOfMonth());
        }
        $out['impact'] = $this->changes->jobImpact($task->getClientJob(), $derived)->without($task)->toArray();

        return $out;
    }

    /**
     * Postavlja rok samo za ovaj zadatak; sa $future i na buduće periode posla klijenta.
     *
     * @return ?ScheduleImpact uticaj na ostale zadatke kad je prilagođen raspored posla, inače null
     */
    public function setDue(Task $task, \DateTimeImmutable $newDue, bool $future, bool $lastDay, ?User $actor): ?ScheduleImpact
    {
        $task->setDueDate($newDue);
        if ($this->isScheduled($task)) {
            $task->setDueDateManuallySet(true);
        }
        // sačuvaj prvo ručni rok: upit preračuna čita bazu i ovaj zadatak mora da izađe iz njega kao ručno postavljen
        $this->em->flush();
        $impact = null;
        if ($future) {
            $derived = $this->deriveSchedule($task, $newDue, $lastDay);
            if (null === $derived) {
                throw new \DomainException('Za ovaj raspored posla se ne može izvesti raspored za buduće periode.');
            }
            // ovaj zadatak je već ručno postavljen, pa ga preračun ne dira
            $impact = $this->changes->changeJob($task->getClientJob(), $derived, $actor);
        }
        $this->em->flush();

        return $impact;
    }

    /**
     * Vraća rok zadatka na podrazumevani (po rasporedu koji sada važi) i ponovo ga prepušta preračunu.
     */
    public function resetDue(Task $task): void
    {
        $default = $this->resolver->scheduledDue($task);
        if (null === $default) {
            throw new \DomainException('Za ovaj zadatak nema podrazumevanog roka.');
        }
        $task->setDueDate($default)->setDueDateManuallySet(false);
        $this->em->flush();
    }

    /** "svakog 6. u mesecu", "poslednjeg dana u mesecu", "svakog petka" */
    private function phrase(Schedule $schedule): string
    {
        $n = max(1, $schedule->getInterval() ?? 1);
        if (ScheduleType::MONTHLY === $schedule->getType() && 1 === $n) {
            return Schedule::LAST_DAY === $schedule->getDayOfMonth() ? 'poslednjeg dana u mesecu' : sprintf('svakog %d. u mesecu', $schedule->getDayOfMonth());
        }
        if (ScheduleType::WEEKLY === $schedule->getType() && 1 === $n) {
            return lcfirst((string) preg_replace('/ \(.*$/', '', $this->calculator->describe($schedule, false)));
        }

        return lcfirst($this->calculator->describe($schedule, true));
    }
}
