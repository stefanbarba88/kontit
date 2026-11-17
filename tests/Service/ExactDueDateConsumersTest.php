<?php

namespace App\Tests\Service;

use App\Entity\ClientJob;
use App\Entity\Schedule;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Repository\NotificationRepository;
use App\Repository\TaskRepository;
use App\Service\CalendarService;
use App\Service\NotificationService;
use App\Service\TaskGenerationService;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Rok koji pada na vikend ili praznik zbog pravila "tačno taj dan" (EXACT): kako ga vide generator, upit za obaveštenja,
 * komanda app:notifications:due i kartica "Naredna nedelja". (Matrica: ClientJobMatrixTest.)
 */
class ExactDueDateConsumersTest extends DatabaseTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('Admin', Role::ADMIN);
    }

    private function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    /** posao sa 13. u mesecu (13.3.2027. je subota), 5 dana unapred */
    private function monthlyJob(string $shift, string $clientName): ClientJob
    {
        $schedule = Schedule::monthly(13, 5)->setNonWorkingDayShift(\App\Enum\NonWorkingDayShift::from($shift));
        $template = $this->template('PDV '.$shift, $this->category('K'.$shift))->setSchedule($schedule);
        $job = $this->clientJob($this->client($clientName, $this->admin), $template)->setStartDate($this->day('2027-03-01'));
        $this->em->flush();

        return $job;
    }

    private function taskFor(ClientJob $job, string $key): ?Task
    {
        return self::getContainer()->get(TaskRepository::class)->findOneBy(['clientJob' => $job, 'periodKey' => $key]);
    }

    public function testGeneratorCreatesExactTaskOnSaturdayAndShiftedOneOnMonday(): void
    {
        $exact = $this->monthlyJob('EXACT', 'Tacno');
        $next = $this->monthlyJob('NEXT', 'Pomereno');
        // 10.3.: tada nastaje i zadatak sa pomerenim rokom (15.3. minus 5 dana); zadatak sa rokom 13.3. nastaje već 8.3.
        self::getContainer()->get(TaskGenerationService::class)->generate($this->admin, $this->day('2027-03-10'));
        $this->em->flush();

        self::assertSame('2027-03-13', $this->taskFor($exact, '2027-03')->getDueDate()->format('Y-m-d'), 'subota ostaje subota');
        self::assertSame('2027-03-15', $this->taskFor($next, '2027-03')->getDueDate()->format('Y-m-d'), 'postojeće pravilo: ponedeljak');
    }

    public function testGenerateCommandDoesNotShiftExactDeadline(): void
    {
        $job = $this->monthlyJob('EXACT', 'Komanda');
        $app = new Application(self::$kernel);
        $tester = new CommandTester($app->find('app:tasks:generate'));
        $tester->execute(['--date' => '2027-03-08']);
        self::assertSame(0, $tester->getStatusCode());
        $this->em->clear();

        $task = self::getContainer()->get(TaskRepository::class)->findOneBy(['clientJob' => $job->getId(), 'periodKey' => '2027-03']);
        self::assertSame('2027-03-13', $task->getDueDate()->format('Y-m-d'));

        // ponovno pokretanje ne pravi dupli zadatak za isti period
        $tester->execute(['--date' => '2027-03-09']);
        self::assertCount(1, self::getContainer()->get(TaskRepository::class)->findBy(['clientJob' => $job->getId()]));
    }

    public function testWorkableDueUntilIncludesWeekendDeadlineByCalendarDate(): void
    {
        $open = $this->task('Subotnji rok', $this->admin, [$this->admin]);
        $open->setDueDate($this->day('2027-03-13'));
        $done = $this->task('Gotov', $this->admin, [$this->admin], null, null, TaskStatus::ZAVRSENO);
        $done->setDueDate($this->day('2027-03-13'));
        $this->em->flush();
        $repo = self::getContainer()->get(TaskRepository::class);

        self::assertNotContains($open, $repo->findWorkableDueUntil($this->day('2027-03-12')));
        $found = $repo->findWorkableDueUntil($this->day('2027-03-13'));
        self::assertContains($open, $found, 'rok na subotu ulazi u upit čim je limit tog datuma');
        self::assertNotContains($done, $found, 'završen zadatak ne ulazi');
    }

    public function testNotificationsCommandHandlesSaturdayDeadlines(): void
    {
        $today = new \DateTimeImmutable('today');
        $past = $today->modify('last saturday');
        $upcoming = $today->modify('next saturday');
        $overdue = $this->task('Prošla subota', $this->admin, [$this->admin]);
        $overdue->setDueDate($past);
        $soon = $this->task('Naredna subota', $this->admin, [$this->admin]);
        $soon->setDueDate($upcoming);
        $this->em->flush();

        $app = new Application(self::$kernel);
        $tester = new CommandTester($app->find('app:notifications:due'));
        $tester->execute(['--days' => '7']);
        self::assertSame(0, $tester->getStatusCode());

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $paths = self::getContainer()->get(NotificationService::class);
        self::assertTrue($notifications->exists($this->admin, NotificationType::TASK_OVERDUE, $paths->path(['task_show', ['id' => $overdue->getId()]])), 'subotnji rok koji je prošao kasni');
        self::assertTrue($notifications->exists($this->admin, NotificationType::TASK_DUE_SOON, $paths->path(['task_show', ['id' => $soon->getId()]])), 'subotnji rok u narednih 7 dana ističe uskoro');

        // druga vožnja ne pravi duplikate (cron radi svaki dan, i vikendom)
        $tester->execute(['--days' => '7']);
        self::assertStringContainsString('Poslato obaveštenja: 0', $tester->getDisplay());
    }

    public function testWeekCardShowsSaturdayDeadlineInOffDayColumn(): void
    {
        // Voter TASK_VIEW traži prijavljenog korisnika
        self::getContainer()->get('security.token_storage')->setToken(new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($this->admin, 'main', $this->admin->getRoles()));
        $client = $this->client('Alfa', $this->admin);
        $task = $this->task('Subotnji PDV', $this->admin, [$this->admin], $client);
        $task->setDueDate($this->day('2027-03-13'));
        $this->em->flush();

        $week = self::getContainer()->get(CalendarService::class)->week($this->admin, $this->day('2027-03-08'));
        $byDate = [];
        foreach ($week as $d) {
            $byDate[$d['date']->format('Y-m-d')] = $d;
        }

        self::assertSame('Subota', $byDate['2027-03-13']['off']);
        self::assertContains('Subotnji PDV', array_column($byDate['2027-03-13']['items'], 'title'));
        self::assertSame([], $byDate['2027-03-15']['items'] ?? [], 'rok se ne prebacuje na ponedeljak');
    }
}
