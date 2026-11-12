<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Service\TaskListFilters;
use App\Tests\Support\DatabaseTestCase;

/** TaskFilter + TaskRepository::createFilteredQueryBuilder nad pravom bazom (kontit_test, transakcija se poništava). */
class TaskListFilterTest extends DatabaseTestCase
{
    /** @return list<string> naslovi zadataka koje $viewer vidi za dati upit (sortirano) */
    private function titles(User $viewer, array $query, ?Client $client = null, ?User $assignee = null): array
    {
        $filters = self::getContainer()->get(TaskListFilters::class);
        $f = $filters->resolve($query, $viewer, $client, $assignee);
        $titles = array_map(static fn ($t) => $t->getTitle(), $filters->queryBuilder($f, $viewer)->getQuery()->getResult());
        sort($titles);

        return $titles;
    }

    public function testTitleSearchIgnoresCaseAndDiacritics(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $this->task('Račun za novembar', $admin);
        $this->task('Đorđe — PDV prijava', $admin);
        $this->task('Plata', $admin);

        self::assertSame(['Račun za novembar'], $this->titles($admin, ['q' => 'racun']));
        self::assertSame(['Račun za novembar'], $this->titles($admin, ['q' => 'RAČUN ZA']));
        self::assertSame(['Đorđe — PDV prijava'], $this->titles($admin, ['q' => 'dord']));
        self::assertSame(['Đorđe — PDV prijava'], $this->titles($admin, ['q' => 'Djordje']));
        self::assertSame([], $this->titles($admin, ['q' => 'nepostojeci']));
        self::assertCount(3, $this->titles($admin, ['q' => '']));
    }

    public function testTitleSearchFollowsTitleChange(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $task = $this->task('Stari naziv', $admin);
        self::assertSame('stari naziv', $task->getTitleSearch());

        $task->setTitle('Novi Šablon');
        $this->em->flush();
        self::assertSame('novi sablon', $task->getTitleSearch());
        self::assertSame(['Novi Šablon'], $this->titles($admin, ['q' => 'sablon']));
        self::assertSame([], $this->titles($admin, ['q' => 'stari']));
    }

    public function testJobFilterAndStandaloneTasks(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $client = $this->client('Alfa', $admin);
        $pdv = $this->template('PDV prijava', $this->category('Porezi'));
        $plate = $this->template('Obračun plata', $this->category('Plate'));
        $this->task('PDV — Alfa', $admin, [], $client, $this->clientJob($client, $pdv), auto: true);
        $this->task('Plate — Alfa', $admin, [], $client, $this->clientJob($client, $plate));
        $this->task('Samostalni', $admin, [], $client);

        self::assertSame(['Samostalni'], $this->titles($admin, ['job' => 'none']));
        self::assertSame(['PDV — Alfa'], $this->titles($admin, ['job' => (string) $pdv->getId()]));
        self::assertSame(['PDV — Alfa'], $this->titles($admin, ['origin' => 'auto']));
        self::assertSame(['Plate — Alfa', 'Samostalni'], $this->titles($admin, ['origin' => 'manual']));
    }

    public function testClosedTasksHiddenByDefault(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $this->task('Otvoren', $admin);
        $this->task('Gotov', $admin, status: TaskStatus::ZAVRSENO);
        $this->task('Otkazan', $admin, status: TaskStatus::OTKAZANO);

        self::assertSame(['Otvoren'], $this->titles($admin, []));
        self::assertSame(['Gotov', 'Otkazan', 'Otvoren'], $this->titles($admin, ['closed' => '1']));
        self::assertSame(['Gotov'], $this->titles($admin, ['status' => 'ZAVRSENO']), 'izabran status ima prednost nad prekidačem');
        // tab vezan za status ignoriše prekidač
        $this->task('Na proveri', $admin, status: TaskStatus::NA_PROVERI);
        self::assertSame(['Na proveri'], $this->titles($admin, ['tab' => 'review']));
    }

    public function testWorkerSeesOnlyOwnTasksOnClientAndUserPage(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $ana = $this->user('Ana');
        $ben = $this->user('Ben');
        $client = $this->client('Alfa', $ana);
        $this->task('Anin', $admin, [$ana], $client);
        $this->task('Benov', $admin, [$ben], $client);
        $this->task('Admin sam', $admin, [], $client);

        // /tasks i tab klijenta: radnik vidi samo svoj zadatak
        self::assertSame(['Anin'], $this->titles($ana, []));
        self::assertSame(['Anin'], $this->titles($ana, [], $client));
        // njegov tab "Zadaci" vidi samo njegove, a admin vidi sve zadatke klijenta
        self::assertSame(['Anin'], $this->titles($ana, [], null, $ana));
        self::assertSame(['Admin sam', 'Anin', 'Benov'], $this->titles($admin, [], $client));
        // admin na Beninoj stranici vidi samo Benove
        self::assertSame(['Benov'], $this->titles($admin, [], null, $ben));
    }

    public function testRoleFilterOnUserPage(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $ana = $this->user('Ana');
        $this->task('Dodeljen Ani', $admin, [$ana]);
        $this->task('Ana kreirala', $ana);

        self::assertSame(['Dodeljen Ani'], $this->titles($admin, [], null, $ana));
        self::assertSame(['Ana kreirala'], $this->titles($admin, ['role' => 'created'], null, $ana));
        self::assertSame(['Ana kreirala', 'Dodeljen Ani'], $this->titles($admin, ['role' => 'both'], null, $ana));
    }

    public function testLeaderSeesOnlyOwnTeamOnUserPage(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $sef = $this->user('Sef', Role::SEF);
        $member = $this->user('Clan');
        $outsider = $this->user('Tudj');
        $team = (new Team())->setName('Tim A')->setLeader($sef);
        $this->em->persist($team);
        $this->em->persist(new TeamMember($team, $member));
        $other = (new Team())->setName('Tim B')->setLeader($this->user('Sefdva', Role::SEF));
        $this->em->persist($other);
        $this->em->persist(new TeamMember($other, $outsider));
        $this->task('Clanov', $admin, [$member]);
        $this->task('Tudji', $admin, [$outsider]);
        $this->em->flush();

        self::assertSame(['Clanov'], $this->titles($sef, [], null, $member));
        self::assertSame([], $this->titles($sef, [], null, $outsider), 'zadaci tuđeg tima se ne vide');

        $security = self::getContainer()->get('security.helper');
        self::assertTrue($security->isGrantedForUser($sef, 'USER_TASKS_VIEW', $member));
        self::assertFalse($security->isGrantedForUser($sef, 'USER_TASKS_VIEW', $outsider), 'šef iz drugog tima: 403');
        self::assertTrue($security->isGrantedForUser($member, 'USER_TASKS_VIEW', $member), 'sam korisnik');
        self::assertFalse($security->isGrantedForUser($member, 'USER_TASKS_VIEW', $outsider), 'radnik tuđi tab');
        self::assertTrue($security->isGrantedForUser($admin, 'USER_TASKS_VIEW', $outsider), 'admin sve');
    }

    public function testActiveFilterCountAndQuery(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $filters = self::getContainer()->get(TaskListFilters::class);
        self::assertSame(0, $filters->resolve([], $admin)->activeCount());
        $f = $filters->resolve(['q' => 'Račun', 'closed' => '1', 'origin' => 'auto', 'job' => 'none'], $admin);
        self::assertSame(4, $f->activeCount());
        self::assertSame(['q' => 'Račun', 'job' => 'none', 'origin' => 'auto', 'closed' => 1], $f->toQuery());
    }
}
