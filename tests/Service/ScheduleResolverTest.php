<?php

namespace App\Tests\Service;

use App\Entity\ClientJob;
use App\Entity\Schedule;
use App\Enum\NonWorkingDayShift;
use App\Enum\Role;
use App\Repository\TaskRepository;
use App\Service\ScheduleResolver;
use App\Service\TaskGenerationService;
use App\Tests\Support\DatabaseTestCase;

/** ScheduleResolver (SPEC 8g.4): Task (ručni rok) > ClientJob (prilagođeno) > JobTemplate (podrazumevano) i generator nad njim. */
class ScheduleResolverTest extends DatabaseTestCase
{
    private ScheduleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = self::getContainer()->get(ScheduleResolver::class);
    }

    private function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    /** posao sa mesečnim rasporedom u šablonu (dan $day), klijent koji počinje 2027-03-01 */
    private function job(string $clientName, int $templateDay = 3, ?\App\Entity\JobTemplate $template = null): ClientJob
    {
        $worker = $this->user('Radnik'.$clientName);
        $template ??= $this->template('PDV', $this->category('K'))->setSchedule(Schedule::monthly($templateDay, 5));
        $job = $this->clientJob($this->client($clientName, $worker), $template)->setStartDate($this->day('2027-03-01'));
        $this->em->flush();

        return $job;
    }

    public function testClientJobOverridesTemplateAndEmptyOverrideInherits(): void
    {
        $job = $this->job('A');
        self::assertFalse($this->resolver->isCustomized($job));
        self::assertSame(3, $this->resolver->forClientJob($job)->getDayOfMonth());

        $job->setScheduleOverride(Schedule::monthly(6, 5));
        self::assertTrue($this->resolver->isCustomized($job));
        self::assertSame(6, $this->resolver->forClientJob($job)->getDayOfMonth());
        self::assertSame(3, $this->resolver->defaultFor($job)->getDayOfMonth());

        $job->setScheduleOverride(new Schedule());
        self::assertFalse($this->resolver->isCustomized($job));
    }

    public function testManuallySetDueDateBeatsSchedule(): void
    {
        $job = $this->job('A');
        $task = $this->task('T', $job->getClient()->getPrimaryAccountant(), [], $job->getClient(), $job);
        $task->markGenerated($job, '2027-03', 30)->setDueDate($this->day('2027-03-03'));

        self::assertSame('2027-03-03', $this->resolver->effectiveDue($task)->format('Y-m-d'));

        $task->setDueDate($this->day('2027-03-10'))->setDueDateManuallySet(true);
        self::assertSame('2027-03-10', $this->resolver->effectiveDue($task)->format('Y-m-d'));
        self::assertSame('2027-03-03', $this->resolver->scheduledDue($task)->format('Y-m-d'), 'podrazumevani rok ostaje raspored');
    }

    public function testTwoClientsSameJobGetDifferentDueDates(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $first = $this->job('A', 3);
        $second = $this->job('B', 3, $first->getJobTemplate());
        $second->setScheduleOverride(Schedule::monthly(6, 5));
        $this->em->flush();

        self::getContainer()->get(TaskGenerationService::class)->generate($admin, $this->day('2027-03-03'));

        $tasks = self::getContainer()->get(TaskRepository::class);
        $a = $tasks->findOneBy(['clientJob' => $first]);
        $b = $tasks->findOneBy(['clientJob' => $second]);
        self::assertSame('2027-03-03', $a->getDueDate()->format('Y-m-d'));
        // 6.3.2027. je subota, pa se prilagođen rok pomera na ponedeljak
        self::assertSame('2027-03-08', $b->getDueDate()->format('Y-m-d'));
    }

    public function testNonWorkingDayShiftWorksForCustomizedSchedule(): void
    {
        $job = $this->job('A');
        $job->setScheduleOverride(Schedule::monthly(6, 5)->setNonWorkingDayShift(NonWorkingDayShift::PREVIOUS));

        $occ = $this->resolver->occurrences($job, $this->day('2027-03-01'), $this->day('2027-03-31'));
        self::assertCount(1, $occ);
        self::assertSame('2027-03-05', $occ[0]['due']->format('Y-m-d'), 'subota se pomera na petak');
        self::assertTrue($occ[0]['shifted']);
    }

    public function testTemplateChangeLeavesCustomizedAloneAndMovesInheriting(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $plain = $this->job('A', 3);
        $custom = $this->job('B', 3, $plain->getJobTemplate());
        $custom->setScheduleOverride(Schedule::monthly(6, 5));
        $plain->getJobTemplate()->setSchedule(Schedule::monthly(4, 5)); // izmena šablona
        $this->em->flush();

        self::getContainer()->get(TaskGenerationService::class)->generate($admin, $this->day('2027-03-03'));

        $tasks = self::getContainer()->get(TaskRepository::class);
        self::assertSame('2027-03-04', $tasks->findOneBy(['clientJob' => $plain])->getDueDate()->format('Y-m-d'));
        self::assertSame('2027-03-08', $tasks->findOneBy(['clientJob' => $custom])->getDueDate()->format('Y-m-d'));
    }
}
