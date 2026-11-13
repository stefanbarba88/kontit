<?php

namespace App\Tests\Service;

use App\Entity\ActivityLog;
use App\Entity\Schedule;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\NonWorkingDayShift;
use App\Enum\Role;
use App\Enum\ScheduleType;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Service\ScheduleChangeService;
use App\Service\TaskGenerationService;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/** Preračun rokova otvorenih zadataka pri promeni rasporeda (SPEC 8g.5). */
class ScheduleChangeServiceTest extends DatabaseTestCase
{
    private ScheduleChangeService $changes;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->changes = self::getContainer()->get(ScheduleChangeService::class);
        $this->admin = $this->user('Admin', Role::ADMIN);
    }

    private function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    private function generate(string $today): void
    {
        self::getContainer()->get(TaskGenerationService::class)->generate($this->admin, $this->day($today));
        $this->em->flush();
    }

    /** posao sa mesečnim rasporedom (3. u mesecu, 5 dana unapred), zadatak za mart 2027 već generisan */
    private function jobWithTask(string $name = 'A', ?\App\Entity\JobTemplate $template = null): array
    {
        $template ??= $this->template('PDV', $this->category('K'))->setSchedule(Schedule::monthly(3, 5));
        $job = $this->clientJob($this->client($name, $this->user('Radnik'.$name)), $template)->setStartDate($this->day('2027-03-01'));
        $this->em->flush();
        $this->generate('2027-03-01');

        return [$job, self::getContainer()->get(TaskRepository::class)->findOneBy(['clientJob' => $job])];
    }

    private function due(Task $task): string
    {
        return $task->getDueDate()->format('Y-m-d');
    }

    public function testCustomizingMovesOnlyOpenNewTasks(): void
    {
        [$job, $open] = $this->jobWithTask('A');
        self::assertSame('2027-03-03', $this->due($open));

        $inProgress = $this->task('U radu', $this->admin, [], $job->getClient(), $job, TaskStatus::U_RADU);
        $inProgress->markGenerated($job, '2027-04', 30)->setDueDate($this->day('2027-04-03'));
        $done = $this->task('Gotov', $this->admin, [], $job->getClient(), $job, TaskStatus::ZAVRSENO);
        $done->markGenerated($job, '2027-02', 30)->setDueDate($this->day('2027-02-03'));
        $this->em->flush();

        $before = \count(self::getContainer()->get(TaskRepository::class)->findAll());
        $preview = $this->changes->jobImpact($job, Schedule::monthly(10, 5));
        self::assertSame(1, $preview->movedCount());
        self::assertSame('2027-03-03', $this->due($open), 'pregled ništa ne menja');

        $impact = $this->changes->changeJob($job, Schedule::monthly(10, 5), $this->admin);
        self::assertSame(1, $impact->movedCount());
        self::assertSame('2027-03-10', $this->due($open));
        self::assertSame('2027-04-03', $this->due($inProgress), 'zadatak u radu se ne dira');
        self::assertSame('2027-02-03', $this->due($done), 'završen zadatak se ne dira');
        self::assertSame($before, \count(self::getContainer()->get(TaskRepository::class)->findAll()), 'preračun ne pravi ni ne briše zadatke');

        $log = $this->em->getRepository(ActivityLog::class)->findOneBy(['entityType' => ActivityLog::CLIENT, 'entityId' => $job->getClient()->getId(), 'action' => \App\Enum\ActivityAction::UPDATED]);
        self::assertNotNull($log);
        self::assertSame($this->admin, $log->getUser());
        $changes = $log->getChanges();
        self::assertSame(['Podrazumevano: Mesečno, 3.', 'Prilagođeno: Mesečno, 10.'], $changes['Raspored: PDV']);
        self::assertSame('1', $changes['Zadataka sa novim rokom'][1]);
    }

    public function testRevertToDefaultRestoresTemplateDue(): void
    {
        [$job, $task] = $this->jobWithTask();
        $this->changes->changeJob($job, Schedule::monthly(10, 5), $this->admin);
        self::assertSame('2027-03-10', $this->due($task));

        $impact = $this->changes->changeJob($job, new Schedule(), $this->admin);
        self::assertSame(1, $impact->movedCount());
        self::assertSame('2027-03-03', $this->due($task));
        self::assertTrue($job->getScheduleOverride()->isEmpty());
    }

    public function testManuallySetDueSurvivesRecalculationAndTemplateChange(): void
    {
        [$job, $task] = $this->jobWithTask();
        $task->setDueDate($this->day('2027-03-17'))->setDueDateManuallySet(true);
        $this->em->flush();

        $this->changes->changeJob($job, Schedule::monthly(10, 5), $this->admin);
        self::assertSame('2027-03-17', $this->due($task));

        $this->changes->changeJob($job, new Schedule(), $this->admin);
        $template = $job->getJobTemplate();
        $before = $this->changes->persistedSchedule($template);
        $template->setSchedule(Schedule::monthly(4, 5));
        $this->em->flush();
        $this->changes->templateChanged($template, $before, $this->admin);
        self::assertSame('2027-03-17', $this->due($task));
    }

    public function testTemplateChangeRecalculatesOnlyInheritingClients(): void
    {
        [$plain, $plainTask] = $this->jobWithTask('A');
        [$custom, $customTask] = $this->jobWithTask('B', $plain->getJobTemplate());
        $this->changes->changeJob($custom, Schedule::monthly(10, 5), $this->admin);

        $template = $plain->getJobTemplate();
        $before = $this->changes->persistedSchedule($template);
        self::assertSame(3, $before->getDayOfMonth());
        $template->setSchedule(Schedule::monthly(4, 5));

        $preview = $this->changes->templateImpact($template);
        self::assertSame(1, $preview->movedCount(), 'prilagođen klijent nije zahvaćen');

        $this->em->flush();
        $impact = $this->changes->templateChanged($template, $before, $this->admin);
        self::assertSame(1, $impact->movedCount());
        self::assertSame('2027-03-04', $this->due($plainTask));
        self::assertSame('2027-03-10', $this->due($customTask));
    }

    public function testKeyChangeLeavesOldOpenTasksAndGeneratorDoesNotCollide(): void
    {
        [$job, $task] = $this->jobWithTask();
        self::assertSame('2027-03', $task->getPeriodKey());

        $quarterly = (new Schedule())->setType(ScheduleType::QUARTERLY)->setInterval(1)->setDayOfMonth(20)->setMonthOfQuarter(1)->setLeadDays(5)
            ->setNonWorkingDayShift(NonWorkingDayShift::NEXT);
        $preview = $this->changes->jobImpact($job, $quarterly);
        self::assertSame(0, $preview->movedCount());
        self::assertSame([$task], $preview->stranded);
        self::assertSame('PDV', $preview->toArray()['stranded'][0]['job']);

        $impact = $this->changes->changeJob($job, $quarterly, $this->admin);
        self::assertSame([$task], $impact->stranded);
        self::assertSame('2027-03-03', $this->due($task));
        self::assertSame(TaskStatus::NOVO, $task->getStatus());

        // generator po novom ključu: Q1 se pravi za rok 20.4.2027., stari zadatak ostaje
        $this->generate('2027-04-15');
        $tasks = self::getContainer()->get(TaskRepository::class)->findBy(['clientJob' => $job], ['id' => 'ASC']);
        self::assertCount(2, $tasks);
        self::assertSame('2027-03-03', $this->due($tasks[0]));
        self::assertSame('2027-Q1', $tasks[1]->getPeriodKey());
        self::assertSame('2027-04-20', $this->due($tasks[1]));

        // ponovno pokretanje ne pravi duplikate
        $this->generate('2027-04-15');
        self::assertCount(2, self::getContainer()->get(TaskRepository::class)->findBy(['clientJob' => $job]));
    }

    public function testWorkerWithoutRightsCannotManageClientJobs(): void
    {
        [$job] = $this->jobWithTask();
        $worker = $job->getClient()->getPrimaryAccountant();
        $storage = self::getContainer()->get('security.token_storage');
        $checker = self::getContainer()->get('security.authorization_checker');

        $storage->setToken(new UsernamePasswordToken($worker, 'main', $worker->getRoles()));
        self::assertFalse($checker->isGranted('CATALOG_MANAGE'));

        $storage->setToken(new UsernamePasswordToken($this->admin, 'main', $this->admin->getRoles()));
        self::assertTrue($checker->isGranted('CATALOG_MANAGE'));
    }
}
