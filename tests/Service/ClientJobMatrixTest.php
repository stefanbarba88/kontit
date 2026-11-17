<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\ClientPackage;
use App\Entity\ServicePackage;
use App\Entity\Task;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\MatrixState;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Service\ClientJobMatrixService;
use App\Service\MatrixView;
use App\Tests\Support\DatabaseTestCase;

/** Matrica "Pregled poslova po klijentima" (SPEC 8r): brojevi, stanja ćelija, vidljivost, filteri i prozor ćelije. */
class ClientJobMatrixTest extends DatabaseTestCase
{
    /** petak, bez praznika; mart 2027 */
    private const TODAY = '2027-03-05';

    private ?\App\Entity\JobCategory $cat = null;

    private function svc(string $today = self::TODAY): ClientJobMatrixService
    {
        $svc = self::getContainer()->get(ClientJobMatrixService::class);
        $svc->setToday(new \DateTimeImmutable($today));

        return $svc;
    }

    private function package(string $name): ServicePackage
    {
        $p = (new ServicePackage())->setName($name);
        $this->em->persist($p);

        return $p;
    }

    private function give(Client $client, ServicePackage $package): ClientPackage
    {
        $cp = (new ClientPackage())->setClient($client)->setPackage($package)->setAgreedPrice('1000.00')->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $this->em->persist($cp);

        return $cp;
    }

    /** zadatak iz posla koji pripada paketu klijenta (ili individualnog, ako je $cp null) */
    private function job(User $by, Client $client, ?ClientPackage $cp, string $due, TaskStatus $status = TaskStatus::NOVO, array $assignees = []): Task
    {
        static $n = 0;
        $this->cat ??= $this->category('Kategorija');
        $job = $this->clientJob($client, $this->template('Posao '.(++$n), $this->cat));
        $job->setClientPackage($cp);
        $t = $this->task('Posao '.$n, $by, $assignees ?: [$by], $client, $job, $status);
        $t->setDueDate(new \DateTimeImmutable($due));
        $this->em->flush();

        return $t;
    }

    private function manual(User $by, Client $client, string $due, TaskStatus $status = TaskStatus::NOVO): Task
    {
        $t = $this->task('Ručni '.$due, $by, [$by], $client, null, $status);
        $t->setDueDate(new \DateTimeImmutable($due));
        $this->em->flush();

        return $t;
    }

    private function view(User $me, array $query = [], int $perPage = 15, int $page = 1): MatrixView
    {
        return $this->svc()->build($this->svc()->filters($query + ['period' => '2027-03']), $me, $page, $perPage);
    }

    /** @return array<string, array<string, string>> klijent => paket (ili "Ostali") => "gotovo/ukupno:stanje" */
    private function grid(MatrixView $v): array
    {
        $out = [];
        foreach ($v->rows as $r) {
            foreach ($v->columns as $col) {
                $c = $r['cells'][$col->getId()];
                $out[$r['client']->getShortName()][$col->getName()] = $c->done.'/'.$c->total.':'.$c->state->value;
            }
            $out[$r['client']->getShortName()]['Ostali'] = $r['other']->done.'/'.$r['other']->total.':'.$r['other']->state->value;
        }

        return $out;
    }

    public function testCountsForThreeClientsAndTwoPackages(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w = $this->user('Radnik');
        $p1 = $this->package('Paket 1');
        $p2 = $this->package('Paket 2');
        $c1 = $this->client('Alfa', $w);
        $c2 = $this->client('Beta', $w);
        $c3 = $this->client('Gama', $w);
        $this->em->flush();
        $cp11 = $this->give($c1, $p1);
        $cp12 = $this->give($c1, $p2);
        $cp21 = $this->give($c2, $p1);
        $this->give($c3, $p2);
        $this->em->flush();

        foreach ([TaskStatus::ZAVRSENO, TaskStatus::ZAVRSENO, TaskStatus::ZAVRSENO, TaskStatus::U_RADU] as $s) {
            $this->job($admin, $c1, $cp11, '2027-03-25', $s);
        }
        $this->job($admin, $c1, $cp12, '2027-03-25', TaskStatus::ZAVRSENO);
        $this->job($admin, $c1, $cp12, '2027-03-26', TaskStatus::ZAVRSENO);
        $this->job($admin, $c2, $cp21, '2027-03-25');
        // van perioda se ne broji
        $this->job($admin, $c1, $cp11, '2027-04-02', TaskStatus::ZAVRSENO);

        $grid = $this->grid($this->view($admin));
        self::assertSame('3/4:progress', $grid['Alfa']['Paket 1']);
        self::assertSame('2/2:success', $grid['Alfa']['Paket 2']);
        self::assertSame('0/1:progress', $grid['Beta']['Paket 1']);
        self::assertSame('0/0:none', $grid['Beta']['Paket 2'], 'klijent bez tog paketa dobija crtu');
        self::assertSame('0/0:none', $grid['Gama']['Paket 1']);
        self::assertSame('0/0:none', $grid['Gama']['Paket 2'], 'klijent sa paketom bez zadataka u periodu');
        self::assertSame('0/0:none', $grid['Alfa']['Ostali']);
    }

    public function testCancelledIsNotCountedAndOtherTasksDoNotMixWithPackages(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $p1 = $this->package('Paket 1');
        $c = $this->client('Alfa', $admin);
        $this->em->flush();
        $cp = $this->give($c, $p1);
        $this->em->flush();
        $this->job($admin, $c, $cp, '2027-03-25', TaskStatus::ZAVRSENO);
        $this->job($admin, $c, $cp, '2027-03-25', TaskStatus::OTKAZANO);
        // individualni posao (bez paketa) i ručni zadatak: samo "Ostali taskovi"
        $this->job($admin, $c, null, '2027-03-25', TaskStatus::ZAVRSENO);
        $this->manual($admin, $c, '2027-03-26');
        $this->manual($admin, $c, '2027-03-26', TaskStatus::OTKAZANO);

        $grid = $this->grid($this->view($admin));
        self::assertSame('1/1:success', $grid['Alfa']['Paket 1'], 'otkazano ne ulazi ni u ukupno');
        self::assertSame('1/2:progress', $grid['Alfa']['Ostali']);
    }

    public function testCellStates(): void
    {
        $svc = $this->svc();
        self::assertSame(MatrixState::SUCCESS, $svc->cellState(3, 3, 0, 0));
        self::assertSame(MatrixState::DANGER, $svc->cellState(4, 3, 1, 0));
        self::assertSame(MatrixState::DANGER, $svc->cellState(4, 2, 1, 1), 'zakasnelo pobeđuje žuto');
        self::assertSame(MatrixState::WARNING, $svc->cellState(4, 3, 0, 1));
        self::assertSame(MatrixState::IN_PROGRESS, $svc->cellState(4, 1, 0, 0));
        self::assertSame(MatrixState::NONE, $svc->cellState(0, 0, 0, 0));
    }

    public function testStatesFromRealDeadlinesAndWorkingDays(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $p = $this->package('Paket');
        $clients = [];
        foreach (['Crvena', 'Zuta', 'Ponedeljak', 'Daleko', 'Stara'] as $name) {
            $clients[$name] = $this->client($name, $admin);
        }
        $this->em->flush();
        $cps = [];
        foreach ($clients as $name => $c) {
            $cps[$name] = $this->give($c, $p);
        }
        $this->em->flush();

        $this->job($admin, $clients['Crvena'], $cps['Crvena'], '2027-03-04'); // juče, otvoren
        $this->job($admin, $clients['Crvena'], $cps['Crvena'], '2027-03-30', TaskStatus::ZAVRSENO);
        $this->job($admin, $clients['Zuta'], $cps['Zuta'], '2027-03-09'); // utorak = 2. radni dan
        $this->job($admin, $clients['Ponedeljak'], $cps['Ponedeljak'], '2027-03-08'); // petak → ponedeljak je 1. radni dan
        $this->job($admin, $clients['Daleko'], $cps['Daleko'], '2027-03-11'); // četvrtak = 4. radni dan, van prozora
        $this->job($admin, $clients['Stara'], $cps['Stara'], '2027-03-01', TaskStatus::ZAVRSENO); // stari rok, ali završeno
        $this->job($admin, $clients['Stara'], $cps['Stara'], '2027-03-02', TaskStatus::ZAVRSENO);

        $svc = $this->svc();
        self::assertSame('2027-03-10', $svc->soonUntil()->format('Y-m-d'), 'petak + 3 radna dana = sreda');
        $grid = $this->grid($this->view($admin));
        self::assertSame('1/2:danger', $grid['Crvena']['Paket']);
        self::assertSame('0/1:warning', $grid['Zuta']['Paket']);
        self::assertSame('0/1:warning', $grid['Ponedeljak']['Paket']);
        self::assertSame('0/1:progress', $grid['Daleko']['Paket']);
        self::assertSame('2/2:success', $grid['Stara']['Paket'], 'sve završeno sa starim rokom nikad nije crveno');
        $cell = $this->view($admin)->rows[0]['cells'][$p->getId()];
        self::assertSame('1 od 2 gotovo, 1 kasni', $cell->summary);
    }

    /** Rok "tačno taj dan" na vikendu (EXACT): stanja ćelije se računaju po kalendarskom datumu roka, a prozor "uskoro" po radnim danima. */
    public function testStatesForDeadlinesOnWeekendFromExactRule(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $p = $this->package('Paket');
        $names = ['Subota', 'Posle', 'Nedelja'];
        $clients = [];
        foreach ($names as $name) {
            $clients[$name] = $this->client($name, $admin);
        }
        $this->em->flush();
        $cps = [];
        foreach ($clients as $name => $c) {
            $cps[$name] = $this->give($c, $p);
        }
        $this->em->flush();

        $this->job($admin, $clients['Subota'], $cps['Subota'], '2027-03-06'); // subota, unutar prozora (petak + 3 radna dana = sreda 10.3.)
        $this->job($admin, $clients['Posle'], $cps['Posle'], '2027-03-13'); // sledeća subota, van prozora
        $this->job($admin, $clients['Nedelja'], $cps['Nedelja'], '2027-03-07'); // nedelja, unutar prozora

        $grid = $this->grid($this->view($admin));
        self::assertSame('0/1:warning', $grid['Subota']['Paket']);
        self::assertSame('0/1:progress', $grid['Posle']['Paket']);
        self::assertSame('0/1:warning', $grid['Nedelja']['Paket']);

        // u nedelju 7.3. subotnji rok (neradni dan) još ne kasni, a od ponedeljka 8.3. kasni obojica
        $sunday = $this->gridAt('2027-03-07', $admin);
        self::assertSame('0/1:warning', $sunday['Subota']['Paket'], 'rok na neradni dan ne kasni dok ne prođe prvi radni dan posle njega');
        self::assertSame('0/1:warning', $sunday['Nedelja']['Paket']);
        $monday = $this->gridAt('2027-03-08', $admin);
        self::assertSame('0/1:danger', $monday['Subota']['Paket']);
        self::assertSame('0/1:danger', $monday['Nedelja']['Paket']);
    }

    /** Praznik usred nedelje i kolektivni odmor: isto pravilo kao za vikend; rok na radni dan kasni kao i do sada. */
    public function testOverdueStatesAroundHolidayAndCollectiveLeave(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $p = $this->package('Paket');
        $clients = [];
        foreach (['Praznik', 'Odmor', 'Radni'] as $name) {
            $clients[$name] = $this->client($name, $admin);
        }
        $this->em->persist(new \App\Entity\NonWorkingDay(\App\Enum\NonWorkingDayType::HOLIDAY_ONCE, 'Praznik', new \DateTimeImmutable('2027-03-10')));
        $this->em->persist(new \App\Entity\NonWorkingDay(\App\Enum\NonWorkingDayType::COLLECTIVE_LEAVE, 'Odmor', new \DateTimeImmutable('2027-03-22'), new \DateTimeImmutable('2027-03-26')));
        $this->em->flush();
        $workingDays = self::getContainer()->get(\App\Service\WorkingDayService::class);
        $workingDays->invalidate();
        try {
            $cps = [];
            foreach ($clients as $name => $c) {
                $cps[$name] = $this->give($c, $p);
            }
            $this->em->flush();
            $this->job($admin, $clients['Praznik'], $cps['Praznik'], '2027-03-10'); // sreda, praznik
            $this->job($admin, $clients['Odmor'], $cps['Odmor'], '2027-03-24'); // sreda usred odmora
            $this->job($admin, $clients['Radni'], $cps['Radni'], '2027-03-09'); // utorak, radni dan

            $onHoliday = $this->gridAt('2027-03-10', $admin);
            self::assertSame('0/1:warning', $onHoliday['Praznik']['Paket'], 'na sam praznik rok ne kasni');
            self::assertSame('0/1:danger', $onHoliday['Radni']['Paket'], 'utorak kasni na praznik, kao i do sada');
            $after = $this->gridAt('2027-03-11', $admin);
            self::assertSame('0/1:danger', $after['Praznik']['Paket'], 'kasni od prvog radnog dana posle praznika');

            self::assertSame('0/1:warning', $this->gridAt('2027-03-28', $admin)['Odmor']['Paket'], 'nedelja posle odmora: još ne kasni');
            self::assertSame('0/1:danger', $this->gridAt('2027-03-29', $admin)['Odmor']['Paket'], 'prvi radni dan posle odmora');
        } finally {
            $workingDays->invalidate(); // transakcija se vraća, pa i keširani neradni dani moraju
        }
    }

    /** @return array<string, array<string, string>> */
    private function gridAt(string $today, User $me): array
    {
        $svc = $this->svc($today);

        return $this->grid($svc->build($svc->filters(['period' => '2027-03']), $me, 1, 15));
    }

    public function testWorkerSeesOnlyOwnClientsAndOwnTasks(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w = $this->user('Radnik');
        $other = $this->user('Drugi');
        $p = $this->package('Paket');
        $mine = $this->client('Moj', $w);
        $theirs = $this->client('Tudj', $other);
        $shared = $this->client('Zajednicki', $other); // radnik je backup
        $shared->setBackupAccountant($w);
        $this->em->flush();
        $cpm = $this->give($mine, $p);
        $cpt = $this->give($theirs, $p);
        $cps = $this->give($shared, $p);
        $this->em->flush();
        $this->job($admin, $mine, $cpm, '2027-03-25', TaskStatus::ZAVRSENO, [$w]);
        $this->job($admin, $theirs, $cpt, '2027-03-25', TaskStatus::ZAVRSENO, [$other]);
        $this->job($admin, $shared, $cps, '2027-03-25', TaskStatus::ZAVRSENO, [$w]);
        $this->job($admin, $shared, $cps, '2027-03-25', TaskStatus::ZAVRSENO, [$other]); // zadatak kolege: radnik ga ne vidi

        $grid = $this->grid($this->view($w));
        self::assertSame(['Moj', 'Zajednicki'], array_keys($grid), 'tuđ klijent se ne vidi');
        self::assertSame('1/1:success', $grid['Moj']['Paket']);
        self::assertSame('1/1:success', $grid['Zajednicki']['Paket'], 'broji se samo zadatak koji radnik sme da vidi');
        $adminGrid = $this->grid($this->view($admin));
        self::assertSame('2/2:success', $adminGrid['Zajednicki']['Paket']);
        self::assertCount(3, $adminGrid);
    }

    public function testLeaderSeesOnlyOwnTeamClients(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $sef = $this->user('Sef', Role::SEF);
        $member = $this->user('Clan');
        $outsider = $this->user('Tudj');
        $team = (new Team())->setName('Tim A')->setLeader($sef);
        $this->em->persist($team);
        $this->em->persist(new TeamMember($team, $member));
        $p = $this->package('Paket');
        $own = $this->client('Timski', $member);
        $foreign = $this->client('Strani', $outsider);
        $this->em->flush();
        $this->job($admin, $own, $this->give($own, $p), '2027-03-25', TaskStatus::ZAVRSENO, [$member]);
        $this->job($admin, $foreign, $this->give($foreign, $p), '2027-03-25', TaskStatus::ZAVRSENO, [$outsider]);
        $this->em->flush();

        $grid = $this->grid($this->view($sef));
        self::assertSame(['Timski'], array_keys($grid));
        self::assertSame('1/1:success', $grid['Timski']['Paket']);
    }

    public function testFilters(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $ana = $this->user('Ana');
        $ben = $this->user('Ben');
        $p1 = $this->package('Paket 1');
        $p2 = $this->package('Paket 2');
        $dj = $this->client('Đorđević', $ana);
        $dj->setBackupAccountant($ben);
        $dz = $this->client('Zoran', $ben);
        $this->em->flush();
        $cpDj = $this->give($dj, $p1);
        $cpDz = $this->give($dz, $p2);
        $this->em->flush();
        $this->job($admin, $dj, $cpDj, '2027-03-25', TaskStatus::ZAVRSENO);
        $this->job($admin, $dz, $cpDz, '2027-03-04'); // kasni

        // pretraga bez kvačica, đ = dj
        self::assertSame(['Đorđević'], array_keys($this->grid($this->view($admin, ['q' => 'djordjevic']))));
        self::assertSame(['Đorđević'], array_keys($this->grid($this->view($admin, ['q' => 'ĐORĐ']))));
        // zaposleni: glavni ili backup
        self::assertEqualsCanonicalizing(['Đorđević', 'Zoran'], array_keys($this->grid($this->view($admin, ['user' => (string) $ben->getId()]))));
        self::assertSame(['Đorđević'], array_keys($this->grid($this->view($admin, ['user' => (string) $ana->getId()]))));
        // paket: samo ta kolona i klijenti koji je imaju
        $v = $this->view($admin, ['pkg' => (string) $p2->getId()]);
        self::assertSame(['Zoran'], array_keys($this->grid($v)));
        self::assertSame(['Paket 2'], array_map(static fn (ServicePackage $x) => $x->getName(), $v->columns));
        // samo nezavršeno i samo problematični
        self::assertSame(['Zoran'], array_keys($this->grid($this->view($admin, ['open' => '1']))));
        self::assertSame(['Zoran'], array_keys($this->grid($this->view($admin, ['problem' => '1']))));
        // izbor perioda: u aprilu nema zadataka
        $april = $this->grid($this->svc()->build($this->svc()->filters(['period' => '2027-04']), $admin));
        self::assertSame('0/0:none', $april['Zoran']['Paket 2']);
        // brojači zaglavlja i zakasneli van perioda
        $v = $this->view($admin);
        self::assertSame(1, $v->dangerCells);
        self::assertSame(1, $v->overdueTasks);
        $aprilView = $this->svc()->build($this->svc()->filters(['period' => '2027-04']), $admin);
        self::assertSame(0, $aprilView->dangerCells);
        self::assertSame(1, $aprilView->overdueTasks, 'zakasneli se broje nezavisno od perioda');
    }

    public function testClientWithoutPackagesAndPaging(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        for ($i = 1; $i <= 5; ++$i) {
            $this->client('Klijent '.$i, $admin);
        }
        $this->em->flush();
        $v = $this->view($admin, [], 2, 2);
        self::assertSame(5, $v->total);
        self::assertCount(2, $v->rows);
        self::assertSame([], $v->columns, 'bez paketa nema kolona, ostaju "Ostali taskovi"');
        self::assertTrue($v->hasMore());
        self::assertFalse($this->view($admin, [], 2, 3)->hasMore());
    }

    public function testCellDetailsMatchCellAndLimitToEightRows(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w = $this->user('Radnik');
        $p = $this->package('Paket');
        $c = $this->client('Alfa', $w);
        $this->em->flush();
        $cp = $this->give($c, $p);
        $this->em->flush();
        $this->job($admin, $c, $cp, '2027-03-04', TaskStatus::U_RADU, [$w]); // kasni
        $this->job($admin, $c, $cp, '2027-03-08', TaskStatus::NOVO, [$w]); // uskoro
        for ($i = 0; $i < 6; ++$i) {
            $this->job($admin, $c, $cp, '2027-03-25', TaskStatus::NOVO, [$w]);
        }
        for ($i = 0; $i < 3; ++$i) {
            $this->job($admin, $c, $cp, '2027-03-20', TaskStatus::ZAVRSENO, [$w]);
        }
        $this->job($admin, $c, $cp, '2027-03-20', TaskStatus::OTKAZANO, [$w]);
        $this->job($admin, $c, null, '2027-03-20', TaskStatus::NOVO, [$w]); // "Ostali", ne ulazi u paket

        $svc = $this->svc();
        $f = $svc->filters(['period' => '2027-03']);
        $cell = $svc->build($f, $w)->rows[0]['cells'][$p->getId()];
        $d = $svc->cellDetails($w, $c, $p, $f);
        self::assertSame($cell->total, $d['total']);
        self::assertSame($cell->done, $d['done']);
        self::assertSame($cell->summary, $d['summary']);
        self::assertSame($cell->state->value, $d['state']);
        self::assertSame(11, $d['total']);
        self::assertSame(['Kasni', 'Ističe uskoro', 'U toku'], array_column($d['groups'], 'label'), 'grupe redom; 8 redova je potrošeno pre "Završeno"');
        self::assertSame(8, array_sum(array_map(static fn (array $g) => \count($g['rows']), $d['groups'])), 'najviše 8 redova');
        self::assertSame(3, $d['more'], 'i još 3 zadatka');
        self::assertStringContainsString('package='.$p->getId(), $d['listUrl']);

        $other = $svc->cellDetails($w, $c, null, $f);
        self::assertSame(1, $other['total'], '"Ostali taskovi" samo bez paketa');
        self::assertStringContainsString('package=none', $other['listUrl']);
    }

    public function testBuildQueryCountDoesNotGrowWithClients(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $p1 = $this->package('Paket 1');
        $p2 = $this->package('Paket 2');
        for ($i = 1; $i <= 30; ++$i) {
            $c = $this->client('Klijent '.$i, $admin);
            $this->em->flush();
            foreach ([$p1, $p2] as $p) {
                $this->job($admin, $c, $this->give($c, $p), '2027-03-25');
            }
        }
        $this->em->flush();
        $this->em->clear();
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $admin = $this->em->getRepository(User::class)->findOneBy(['firstName' => 'Admin']);
        $holder->reset();
        $v = $this->view($admin, [], 50);
        foreach ($v->rows as $r) {
            $r['client']->getShortName();
            $r['primary']?->getFullName();
            $r['backup']?->getFullName();
        }
        $n = \count($holder->getData()['default'] ?? []);
        self::assertCount(30, $v->rows);
        self::assertLessThanOrEqual(8, $n, 'upiti ne smeju da rastu sa brojem klijenata');
    }

    public function testTaskListPackageFilterAndNoPackage(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $p1 = $this->package('Paket 1');
        $c = $this->client('Alfa', $admin);
        $this->em->flush();
        $cp = $this->give($c, $p1);
        $this->em->flush();
        $inPackage = $this->job($admin, $c, $cp, '2027-03-25');
        $individual = $this->job($admin, $c, null, '2027-03-25');
        $manual = $this->manual($admin, $c, '2027-03-26');

        $filters = self::getContainer()->get(\App\Service\TaskListFilters::class);
        $titles = function (array $query) use ($filters, $admin): array {
            $f = $filters->resolve($query + ['closed' => '1'], $admin);

            return array_map(static fn (Task $t) => $t->getId(), $filters->queryBuilder($f, $admin)->getQuery()->getResult());
        };
        self::assertSame([$inPackage->getId()], $titles(['package' => (string) $p1->getId()]));
        self::assertEqualsCanonicalizing([$individual->getId(), $manual->getId()], $titles(['package' => 'none']));
        self::assertCount(3, $titles([]));
        $f = $filters->resolve(['package' => 'none'], $admin);
        self::assertSame('none', $f->toQuery()['package']);
        self::assertSame(1, $f->activeCount());
        self::assertStringContainsString('Paket: bez paketa', $filters->describe($f));
    }
}
