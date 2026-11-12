<?php

namespace App\Tests\Service;

use App\Entity\NonWorkingDay;
use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\NonWorkingDayType;
use App\Enum\Role;
use App\Enum\TodoRepeat;
use App\Repository\TodoItemRepository;
use App\Service\TodoClock;
use App\Service\TodoService;
use App\Service\WorkingDayService;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/** Moja lista: privatnost, UTC/Belgrade, ponavljanje i "Poništi". */
class TodoTest extends DatabaseTestCase
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

    private function can(User $user, string $attribute, mixed $subject): bool
    {
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return self::getContainer()->get('security.authorization_checker')->isGranted($attribute, $subject);
    }

    /** lokalno (Belgrade) vreme kao UTC trenutak */
    private function at(string $date, string $time = '09:00'): \DateTimeImmutable
    {
        return $this->clock->fromLocal($date, $time);
    }

    private function now(string $date, string $time = '10:00'): void
    {
        $this->clock->setNow($this->at($date, $time));
    }

    private function repeating(User $owner, TodoRepeat $repeat, string $dueDate, bool $time = true): TodoItem
    {
        $item = (new TodoItem($owner, 'Ponavljam'))->setRepeat($repeat)->setDue($time ? $this->at($dueDate) : $this->clock->startOfDay($dueDate), $time);
        $this->todo->save($item);

        return $item;
    }

    private function localDue(?TodoItem $item): ?string
    {
        return $item?->getDueAt()?->setTimezone($this->clock->zone())->format('Y-m-d H:i');
    }

    public function testOnlyOwnerSeesAndChangesItem(): void
    {
        $owner = $this->user('Vlasnik');
        $admin = $this->user('Admin', Role::ADMIN);
        $super = $this->user('Super', Role::SUPERADMIN);
        $this->em->flush();
        $item = $this->todo->add($owner, 'Tajna');
        $this->todo->add($admin, 'Admin stavka');
        $this->em->flush();

        foreach (['TODO_VIEW', 'TODO_EDIT', 'TODO_DELETE'] as $attr) {
            self::assertTrue($this->can($owner, $attr, $item));
            self::assertFalse($this->can($admin, $attr, $item), $attr);
            self::assertFalse($this->can($super, $attr, $item), $attr);
        }

        $repo = self::getContainer()->get(TodoItemRepository::class);
        self::assertNotNull($repo->findOwned($item->getId(), $owner));
        self::assertNull($repo->findOwned($item->getId(), $admin), 'tuđa stavka se ne učitava (kontroler vraća 404)');
        self::assertNull($repo->findOwned($item->getId(), $super));
        self::assertSame([], $repo->search($super, 'Tajna'));
        $titles = [];
        foreach ($this->todo->sections($super) as $list) {
            foreach ($list as $i) {
                $titles[] = $i->getTitle();
            }
        }
        self::assertSame([], $titles, 'superadmin ne vidi tudje stavke ni u sekcijama');
        self::assertSame(0, $this->todo->dueCount($admin));
    }

    public function testStoredInUtcAndShownInBelgrade(): void
    {
        $owner = $this->user('Vlasnik');
        // 09:00 po Beogradu: leto UTC+2, zima UTC+1
        $summer = (new TodoItem($owner, 'Leto'))->setRemindAt($this->at('2026-10-24', '09:00'));
        $winter = (new TodoItem($owner, 'Zima'))->setRemindAt($this->at('2026-10-26', '09:00'));
        $this->todo->save($summer);
        $this->todo->save($winter);
        $this->em->flush();

        $db = $this->em->getConnection();
        self::assertSame('2026-10-24 07:00:00', $db->fetchOne('SELECT remind_at FROM todo_item WHERE id = ?', [$summer->getId()]));
        self::assertSame('2026-10-26 08:00:00', $db->fetchOne('SELECT remind_at FROM todo_item WHERE id = ?', [$winter->getId()]));
        $this->em->clear();
        $loaded = $this->em->find(TodoItem::class, $winter->getId());
        self::assertSame('UTC', $loaded->getRemindAt()->getTimezone()->getName());
        self::assertSame('09:00', $this->clock->localTime($loaded->getRemindAt()));
    }

    public function testDateOnlyDueIsOverdueOnlyAfterLocalMidnight(): void
    {
        $owner = $this->user('Vlasnik');
        $item = (new TodoItem($owner, 'Cijeli dan'))->setDue($this->clock->startOfDay('2026-11-12'), false);
        $timed = (new TodoItem($owner, 'U 08:00'))->setDue($this->at('2026-11-12', '08:00'), true);
        $this->todo->save($item);
        $this->todo->save($timed);

        $this->now('2026-11-12', '23:30');
        self::assertSame('today', $this->todo->bucket($item), 'bez vremena: danas ceo dan');
        self::assertSame('overdue', $this->todo->bucket($timed));
        self::assertSame(2, $this->todo->dueCount($owner));
        $this->now('2026-11-13', '00:05');
        self::assertSame('overdue', $this->todo->bucket($item));
    }

    public function testDailyRepeatKeepsLocalTimeAcrossDstChange(): void
    {
        $owner = $this->user('Vlasnik');
        $item = $this->repeating($owner, TodoRepeat::DAILY, '2026-10-24');
        $this->now('2026-10-24');
        $next = $this->todo->complete($item);

        self::assertSame('2026-10-25 09:00', $this->localDue($next));
        $this->em->flush();
        self::assertSame('2026-10-25 08:00:00', $this->em->getConnection()->fetchOne('SELECT due_at FROM todo_item WHERE id = ?', [$next->getId()]));
    }

    public function testOverdueRepeatCountsFromToday(): void
    {
        $owner = $this->user('Vlasnik');
        $item = $this->repeating($owner, TodoRepeat::DAILY, '2026-11-07');
        $this->now('2026-11-12');
        $next = $this->todo->complete($item);

        self::assertSame('2026-11-13 09:00', $this->localDue($next), 'sledeći rok je sutra, ne 08.11.');
        self::assertCount(0, array_filter($this->todo->sections($owner)['overdue'], static fn (TodoItem $i) => !$i->isDone() && $i !== $item));
    }

    public function testWorkdaysSkipWeekendAndHoliday(): void
    {
        $owner = $this->user('Vlasnik');
        $item = $this->repeating($owner, TodoRepeat::WORKDAYS, '2026-11-13'); // petak
        $this->now('2026-11-13');
        self::assertSame('2026-11-16 09:00', $this->localDue($this->todo->complete($item)));

        // ponedeljak 16.11. je praznik: sledeći je utorak
        $this->em->persist(new NonWorkingDay(NonWorkingDayType::HOLIDAY_RECURRING, 'Test praznik', new \DateTimeImmutable('2020-11-16')));
        $this->em->flush();
        self::getContainer()->get(WorkingDayService::class)->invalidate();
        $item2 = $this->repeating($owner, TodoRepeat::WORKDAYS, '2026-11-13');
        self::assertSame('2026-11-17 09:00', $this->localDue($this->todo->complete($item2)));
        self::getContainer()->get(WorkingDayService::class)->invalidate();
    }

    public function testMonthlyOn31stClampsAndDoesNotDrift(): void
    {
        $owner = $this->user('Vlasnik');
        $item = $this->repeating($owner, TodoRepeat::MONTHLY, '2027-01-31');
        $this->now('2027-01-31');
        $feb = $this->todo->complete($item);
        self::assertSame('2027-02-28 09:00', $this->localDue($feb));

        $this->now('2027-02-28');
        $mar = $this->todo->complete($feb);
        self::assertSame('2027-03-31 09:00', $this->localDue($mar), 'dan 31. se pamti, ne kliza na 28.');
    }

    public function testUndoDeletesUntouchedNextItemButKeepsTouchedOne(): void
    {
        $owner = $this->user('Vlasnik');
        $this->now('2026-11-12');
        $item = $this->repeating($owner, TodoRepeat::DAILY, '2026-11-12');
        $repo = self::getContainer()->get(TodoItemRepository::class);

        $next = $this->todo->complete($item);
        $nextId = $next->getId();
        self::assertNotNull($nextId);
        self::assertTrue($this->todo->reopen($item), 'netaknuta nova stavka se briše');
        self::assertFalse($item->isDone());
        $this->em->clear();
        self::assertNull($this->em->find(TodoItem::class, $nextId));

        // druga prilika: korisnik je u međuvremenu izmenio novu stavku, ne briše se
        $item = $this->em->find(TodoItem::class, $item->getId());
        $next = $this->todo->complete($item);
        usleep(1500);
        $next->setTitle('Izmenjena');
        $this->em->flush();
        self::assertFalse($next->isUntouched());
        self::assertFalse($this->todo->reopen($item));
        self::assertNotNull($repo->findRepeatChild($item));
    }
}
