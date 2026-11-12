<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\TodoItemRepository;
use App\Service\TodoClock;
use App\Service\TodoReminderService;
use App\Service\TodoService;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** Podsetnici Moje liste: tačno jednom, odlaganje, zimsko/letnje vreme, bez automatskog podsetnika za rok. */
class TodoReminderTest extends DatabaseTestCase
{
    private TodoClock $clock;
    private TodoService $todo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = self::getContainer()->get(TodoClock::class);
        $this->todo = self::getContainer()->get(TodoService::class);
    }

    protected function tearDown(): void
    {
        $this->clock->setNow(null);
        parent::tearDown();
    }

    private function item(User $owner, string $remindDate, string $remindTime = '09:00', bool $email = false): TodoItem
    {
        $item = (new TodoItem($owner, 'Pozvati klijenta'))->setRemindAt($this->clock->fromLocal($remindDate, $remindTime))->setRemindByEmail($email);
        $this->todo->save($item);

        return $item;
    }

    private function reminders(User $user): int
    {
        return $this->em->getRepository(Notification::class)->count(['user' => $user, 'type' => NotificationType::TODO_REMINDER]);
    }

    private function runCommand(): string
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:todo:send-reminders'));
        $tester->execute([]);

        return $tester->getDisplay();
    }

    public function testReminderIsSentExactlyOnceEvenWhenCommandRunsTwice(): void
    {
        $owner = $this->user('Vlasnik');
        $this->em->flush();
        $item = $this->item($owner, '2026-11-13');
        $this->clock->setNow($this->clock->fromLocal('2026-11-13', '09:00:30'));

        self::assertStringContainsString('Poslato podsetnika: 1', $this->runCommand());
        self::assertSame('', trim($this->runCommand()), 'drugo pokretanje ne šalje ništa');
        self::assertSame(1, $this->reminders($owner));

        // preklapanje: drugi proces pokušava da preuzme isti podsetnik
        $repo = self::getContainer()->get(TodoItemRepository::class);
        self::assertFalse($repo->claimReminder($item->getId(), $this->clock->now()), 'atomski UPDATE: već preuzeto');
    }

    public function testNotYetDueDoneAndDueOnlyItemsAreNotReminded(): void
    {
        $owner = $this->user('Vlasnik');
        $this->em->flush();
        $future = $this->item($owner, '2026-11-13', '10:00');
        $done = $this->item($owner, '2026-11-13', '08:00');
        $this->todo->complete($done);
        // rok sa vremenom, ali bez podsetnika: nikad automatski podsetnik (dopuna C)
        $dueOnly = (new TodoItem($owner, 'Samo rok'))->setDue($this->clock->fromLocal('2026-11-13', '08:30'), true);
        $this->todo->save($dueOnly);

        $this->clock->setNow($this->clock->fromLocal('2026-11-13', '09:00'));
        self::assertSame(0, self::getContainer()->get(TodoReminderService::class)->sendDue());
        self::assertSame(0, $this->reminders($owner));
        self::assertNull($future->getReminderSentAt());
    }

    public function testMailIsQueuedOnlyWhenEnabledOnTheItem(): void
    {
        $owner = $this->user('Vlasnik')->setEmail('vlasnik@test.local');
        $this->em->flush();
        $this->item($owner, '2026-11-13', '09:00', true);
        $this->item($owner, '2026-11-13', '09:00', false);
        $this->clock->setNow($this->clock->fromLocal('2026-11-13', '09:01'));
        self::getContainer()->get(TodoReminderService::class)->sendDue();

        $sent = $this->em->getRepository(Notification::class)->findBy(['user' => $owner, 'type' => NotificationType::TODO_REMINDER], ['id' => 'ASC']);
        self::assertCount(2, $sent);
        self::assertCount(1, array_filter($sent, static fn (Notification $n) => $n->isEmailSent()));
    }

    public function testSnoozeMovesReminderAndSendsAgain(): void
    {
        $owner = $this->user('Vlasnik');
        $this->em->flush();
        $item = $this->item($owner, '2026-11-13');
        $service = self::getContainer()->get(TodoReminderService::class);
        $this->clock->setNow($this->clock->fromLocal('2026-11-13', '09:00'));
        $service->sendDue();
        self::assertSame(1, $this->reminders($owner));

        // toast je prikazan, pa korisnik odlaže za 10 min: oba "poslato" i "prikazano" se brišu
        self::getContainer()->get(TodoItemRepository::class)->claimShown($item->getId(), $owner, $this->clock->now());
        $this->em->refresh($item);
        self::assertNotNull($item->getShownAt());
        $this->todo->snooze($item, '10m');
        self::assertSame('09:10', $this->clock->localTime($item->getRemindAt()));
        self::assertNull($item->getReminderSentAt());
        self::assertNull($item->getShownAt());

        $this->clock->setNow($this->clock->fromLocal('2026-11-13', '09:09'));
        self::assertSame(0, $service->sendDue(), 'još nije stiglo');
        $this->clock->setNow($this->clock->fromLocal('2026-11-13', '09:10'));
        self::assertSame(1, $service->sendDue());
        self::assertSame(2, $this->reminders($owner));
    }

    public function testSnoozeTomorrowIsNextWorkingDayAtNine(): void
    {
        $owner = $this->user('Vlasnik');
        $this->em->flush();
        $item = $this->item($owner, '2026-11-13');
        $this->clock->setNow($this->clock->fromLocal('2026-11-13', '16:00')); // petak
        $this->todo->snooze($item, 'tomorrow');
        self::assertSame('2026-11-16 09:00', $this->clock->toLocal($item->getRemindAt())->format('Y-m-d H:i'), 'petak → ponedeljak');

        $this->todo->snooze($item, '1h');
        self::assertSame('17:00', $this->clock->localTime($item->getRemindAt()));
        $this->expectException(\DomainException::class);
        $this->todo->snooze($item, 'zauvek');
    }

    public function testNineOClockBelgradeArrivesAtNineBeforeAndAfterWinterTime(): void
    {
        $owner = $this->user('Vlasnik');
        $this->em->flush();
        $service = self::getContainer()->get(TodoReminderService::class);
        // 24.10.2026. letnje vreme (UTC+2) i 26.10.2026. zimsko (UTC+1): isto lokalno vreme 09:00
        $summer = $this->item($owner, '2026-10-24');
        $winter = $this->item($owner, '2026-10-26');
        self::assertSame('07:00', $summer->getRemindAt()->format('H:i'));
        self::assertSame('08:00', $winter->getRemindAt()->format('H:i'));

        $this->clock->setNow(new \DateTimeImmutable('2026-10-24 06:59:59', new \DateTimeZone('UTC')));
        self::assertSame(0, $service->sendDue());
        $this->clock->setNow(new \DateTimeImmutable('2026-10-24 07:00:00', new \DateTimeZone('UTC')));
        self::assertSame(1, $service->sendDue(), 'leto: 09:00 po Beogradu = 07:00 UTC');

        $this->clock->setNow(new \DateTimeImmutable('2026-10-26 07:00:00', new \DateTimeZone('UTC')));
        self::assertSame(0, $service->sendDue(), 'zima: u 07:00 UTC je tek 08:00 po Beogradu');
        $this->clock->setNow(new \DateTimeImmutable('2026-10-26 08:00:00', new \DateTimeZone('UTC')));
        self::assertSame(1, $service->sendDue(), 'zima: 09:00 po Beogradu = 08:00 UTC');
    }
}
