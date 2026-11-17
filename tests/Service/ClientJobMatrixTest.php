<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\ClientPackage;
use App\Entity\JobCategory;
use App\Entity\JobTemplate;
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

/** Matrica "Pregled poslova po klijentima" (SPEC 8r): kolone po poslu izabrane kategorije, status ćelije, vidljivost, filteri i prozor ćelije. */
class ClientJobMatrixTest extends DatabaseTestCase
{
    /** petak, bez praznika; mart 2027 */
    private const TODAY = '2027-03-05';

    /** @var array<string, JobCategory> */
    private array $cats = [];

    /** @var array<string, JobTemplate> */
    private array $tpls = [];

    /** @var array<string, ClientJob> */
    private array $clientJobs = [];

    private function svc(string $today = self::TODAY): ClientJobMatrixService
    {
        $svc = self::getContainer()->get(ClientJobMatrixService::class);
        $svc->setToday(new \DateTimeImmutable($today));

        return $svc;
    }

    /** kategorije imaju negativnu poziciju da bi bile prve: "Kat A" pre "Kat B" */
    private function cat(string $name): JobCategory
    {
        if (!isset($this->cats[$name])) {
            $this->cats[$name] = $this->category($name)->setPosition(-100 + \count($this->cats));
            $this->em->flush();
        }

        return $this->cats[$name];
    }

    private function tpl(string $name, string $cat = 'Kat A'): JobTemplate
    {
        if (!isset($this->tpls[$cat.$name])) {
            $this->tpls[$cat.$name] = $this->template($name, $this->cat($cat));
            $this->em->flush();
        }

        return $this->tpls[$cat.$name];
    }

    /** zadatak iz posla klijenta (ClientJob se pravi jednom po klijentu i poslu) */
    private function job(User $by, Client $client, JobTemplate $tpl, string $due, TaskStatus $status = TaskStatus::NOVO, array $assignees = []): Task
    {
        static $n = 0;
        $key = $client->getId().'-'.$tpl->getId();
        if (!isset($this->clientJobs[$key])) {
            $this->clientJobs[$key] = $this->clientJob($client, $tpl);
            $this->em->flush();
        }
        $t = $this->task('Zadatak '.(++$n), $by, $assignees ?: [$by], $client, $this->clientJobs[$key], $status);
        $t->setDueDate(new \DateTimeImmutable($due));
        $this->em->flush();

        return $t;
    }

    private function view(User $me, array $query = [], int $perPage = 15, int $page = 1): MatrixView
    {
        return $this->svc()->build($this->svc()->filters($query + ['period' => '2027-03']), $me, $page, $perPage);
    }

    /** @return array<string, array<string, string>> klijent => posao => stanje */
    private function grid(MatrixView $v): array
    {
        $out = [];
        foreach ($v->rows as $r) {
            foreach ($v->columns as $col) {
                $out[$r['client']->getShortName()][$col->getName()] = $r['cells'][$col->getId()]->state->value;
            }
        }

        return $out;
    }

    /** @return array<string, array<string, string>> */
    private function gridAt(string $today, User $me): array
    {
        $svc = $this->svc($today);

        return $this->grid($svc->build($svc->filters(['period' => '2027-03']), $me, 1, 15));
    }

    public function testColumnsAreJobsOfSelectedCategoryAndDefaultIsFirstCategory(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $a = $this->client('Alfa', $admin);
        $b = $this->client('Beta', $admin);
        $this->em->flush();
        $pdv = $this->tpl('PDV');
        $plate = $this->tpl('Plate');
        $other = $this->tpl('Godisnji', 'Kat B');
        $this->job($admin, $a, $pdv, '2027-03-25', TaskStatus::ZAVRSENO);
        $this->job($admin, $a, $plate, '2027-03-25');
        $this->job($admin, $b, $pdv, '2027-03-25');
        $this->job($admin, $b, $other, '2027-03-25');

        $v = $this->view($admin);
        self::assertSame('Kat A', $v->category->getName(), 'bez parametra: prva kategorija po redosledu');
        self::assertSame(['PDV', 'Plate'], array_map(static fn (JobTemplate $j) => $j->getName(), $v->columns));
        $grid = $this->grid($v);
        self::assertSame('success', $grid['Alfa']['PDV']);
        self::assertSame('progress', $grid['Alfa']['Plate']);
        self::assertSame('progress', $grid['Beta']['PDV']);
        self::assertSame('none', $grid['Beta']['Plate'], 'klijent nema taj posao: crtica');

        $catB = $this->view($admin, ['cat' => (string) $this->cat('Kat B')->getId()]);
        self::assertSame(['Godisnji'], array_map(static fn (JobTemplate $j) => $j->getName(), $catB->columns));
        self::assertSame($this->cat('Kat B')->getId(), $catB->filters->categoryId);

        foreach (['', 'abc', '0', '-5', '999999', 'cat'] as $bad) {
            self::assertSame('Kat A', $this->view($admin, ['cat' => $bad])->category->getName(), 'neispravan cat "'.$bad.'" = prva kategorija');
        }
    }

    public function testCellShowsWorstStateAndGreenOnlyWhenAllTasksDone(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $c = $this->client('Alfa', $admin);
        $this->em->flush();
        $weekly = $this->tpl('Nedeljni');
        $this->job($admin, $c, $weekly, '2027-03-12', TaskStatus::ZAVRSENO);
        $this->job($admin, $c, $weekly, '2027-03-19', TaskStatus::ZAVRSENO);
        self::assertSame('success', $this->grid($this->view($admin))['Alfa']['Nedeljni'], 'svi urađeni');

        $this->job($admin, $c, $weekly, '2027-03-26'); // jedan još u toku
        self::assertSame('progress', $this->grid($this->view($admin))['Alfa']['Nedeljni']);

        $this->job($admin, $c, $weekly, '2027-03-08'); // uskoro (ponedeljak)
        self::assertSame('warning', $this->grid($this->view($admin))['Alfa']['Nedeljni'], 'uskoro ističe pobeđuje u toku');

        $this->job($admin, $c, $weekly, '2027-03-01'); // kasni
        self::assertSame('danger', $this->grid($this->view($admin))['Alfa']['Nedeljni'], 'kasni pobeđuje sve');

        $this->job($admin, $c, $weekly, '2027-03-20', TaskStatus::OTKAZANO);
        self::assertSame(5, $this->view($admin)->rows[0]['cells'][$weekly->getId()]->total, 'otkazani se ne broji');
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
        $t = $this->tpl('Posao');
        $clients = [];
        foreach (['Crvena', 'Zuta', 'Ponedeljak', 'Daleko', 'Stara'] as $name) {
            $clients[$name] = $this->client($name, $admin);
        }
        $this->em->flush();
        $this->job($admin, $clients['Crvena'], $t, '2027-03-04'); // juče, otvoren
        $this->job($admin, $clients['Crvena'], $t, '2027-03-30', TaskStatus::ZAVRSENO);
        $this->job($admin, $clients['Zuta'], $t, '2027-03-09'); // utorak = 2. radni dan
        $this->job($admin, $clients['Ponedeljak'], $t, '2027-03-08'); // petak → ponedeljak je 1. radni dan
        $this->job($admin, $clients['Daleko'], $t, '2027-03-11'); // četvrtak = 4. radni dan, van prozora
        $this->job($admin, $clients['Stara'], $t, '2027-03-01', TaskStatus::ZAVRSENO); // stari rok, ali završeno
        $this->job($admin, $clients['Stara'], $t, '2027-03-02', TaskStatus::ZAVRSENO);

        self::assertSame('2027-03-10', $this->svc()->soonUntil()->format('Y-m-d'), 'petak + 3 radna dana = sreda');
        $grid = $this->grid($this->view($admin));
        self::assertSame('danger', $grid['Crvena']['Posao']);
        self::assertSame('warning', $grid['Zuta']['Posao']);
        self::assertSame('warning', $grid['Ponedeljak']['Posao']);
        self::assertSame('progress', $grid['Daleko']['Posao']);
        self::assertSame('success', $grid['Stara']['Posao'], 'sve završeno sa starim rokom nikad nije crveno');
        $crvena = array_values(array_filter($this->view($admin)->rows, static fn (array $r) => 'Crvena' === $r['client']->getShortName()))[0];
        self::assertSame('1 od 2 gotovo, 1 kasni', $crvena['cells'][$t->getId()]->summary);
    }

    /** Rok "tačno taj dan" na vikendu (EXACT): stanja se računaju po kalendarskom datumu roka, a prozor "uskoro" po radnim danima. */
    public function testStatesForDeadlinesOnWeekendFromExactRule(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $t = $this->tpl('Posao');
        $clients = [];
        foreach (['Subota', 'Posle', 'Nedelja'] as $name) {
            $clients[$name] = $this->client($name, $admin);
        }
        $this->em->flush();
        $this->job($admin, $clients['Subota'], $t, '2027-03-06');
        $this->job($admin, $clients['Posle'], $t, '2027-03-13'); // sledeća subota, van prozora
        $this->job($admin, $clients['Nedelja'], $t, '2027-03-07');

        $grid = $this->grid($this->view($admin));
        self::assertSame('warning', $grid['Subota']['Posao']);
        self::assertSame('progress', $grid['Posle']['Posao']);
        self::assertSame('warning', $grid['Nedelja']['Posao']);

        $sunday = $this->gridAt('2027-03-07', $admin);
        self::assertSame('warning', $sunday['Subota']['Posao'], 'rok na neradni dan ne kasni dok ne prođe prvi radni dan posle njega');
        $monday = $this->gridAt('2027-03-08', $admin);
        self::assertSame('danger', $monday['Subota']['Posao']);
        self::assertSame('danger', $monday['Nedelja']['Posao']);
    }

    /** Praznik usred nedelje i kolektivni odmor: isto pravilo kao za vikend; rok na radni dan kasni kao i do sada. */
    public function testOverdueStatesAroundHolidayAndCollectiveLeave(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $t = $this->tpl('Posao');
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
            $this->job($admin, $clients['Praznik'], $t, '2027-03-10');
            $this->job($admin, $clients['Odmor'], $t, '2027-03-24');
            $this->job($admin, $clients['Radni'], $t, '2027-03-09');

            $onHoliday = $this->gridAt('2027-03-10', $admin);
            self::assertSame('warning', $onHoliday['Praznik']['Posao'], 'na sam praznik rok ne kasni');
            self::assertSame('danger', $onHoliday['Radni']['Posao']);
            self::assertSame('danger', $this->gridAt('2027-03-11', $admin)['Praznik']['Posao']);
            self::assertSame('warning', $this->gridAt('2027-03-28', $admin)['Odmor']['Posao']);
            self::assertSame('danger', $this->gridAt('2027-03-29', $admin)['Odmor']['Posao']);
        } finally {
            $workingDays->invalidate(); // transakcija se vraća, pa i keširani neradni dani moraju
        }
    }

    public function testWorkerSeesOnlyOwnClientsAndOwnTasks(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w = $this->user('Radnik');
        $other = $this->user('Drugi');
        $mine = $this->client('Moj', $w);
        $theirs = $this->client('Tudj', $other);
        $shared = $this->client('Zajednicki', $other); // radnik je backup
        $shared->setBackupAccountant($w);
        $this->em->flush();
        $t = $this->tpl('Posao');
        $this->job($admin, $mine, $t, '2027-03-25', TaskStatus::ZAVRSENO, [$w]);
        $this->job($admin, $theirs, $t, '2027-03-25', TaskStatus::ZAVRSENO, [$other]);
        $this->job($admin, $shared, $t, '2027-03-25', TaskStatus::ZAVRSENO, [$w]);
        $this->job($admin, $shared, $t, '2027-03-04', TaskStatus::NOVO, [$other]); // zadatak kolege (kasni): radnik ga ne vidi

        $grid = $this->grid($this->view($w));
        self::assertSame(['Moj', 'Zajednicki'], array_keys($grid), 'tuđ klijent se ne vidi');
        self::assertSame('success', $grid['Zajednicki']['Posao'], 'broji se samo zadatak koji radnik sme da vidi');
        $adminGrid = $this->grid($this->view($admin));
        self::assertSame('danger', $adminGrid['Zajednicki']['Posao']);
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
        $own = $this->client('Timski', $member);
        $foreign = $this->client('Strani', $outsider);
        $this->em->flush();
        $t = $this->tpl('Posao');
        $this->job($admin, $own, $t, '2027-03-25', TaskStatus::ZAVRSENO, [$member]);
        $this->job($admin, $foreign, $t, '2027-03-25', TaskStatus::ZAVRSENO, [$outsider]);

        $grid = $this->grid($this->view($sef));
        self::assertSame(['Timski'], array_keys($grid));
        self::assertSame('success', $grid['Timski']['Posao']);
    }

    public function testFilters(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $ana = $this->user('Ana');
        $ben = $this->user('Ben');
        $dj = $this->client('Đorđević', $ana);
        $dj->setBackupAccountant($ben);
        $dz = $this->client('Zoran', $ben);
        $this->em->flush();
        $t1 = $this->tpl('Posao 1');
        $t2 = $this->tpl('Posao 2', 'Kat B');
        $this->job($admin, $dj, $t1, '2027-03-25', TaskStatus::ZAVRSENO);
        $this->job($admin, $dz, $t1, '2027-03-25', TaskStatus::ZAVRSENO);
        $this->job($admin, $dz, $t2, '2027-03-04'); // kasni, ali u drugoj kategoriji

        self::assertSame(['Đorđević'], array_keys($this->grid($this->view($admin, ['q' => 'djordjevic']))));
        self::assertSame(['Đorđević'], array_keys($this->grid($this->view($admin, ['q' => 'ĐORĐ']))));
        self::assertEqualsCanonicalizing(['Đorđević', 'Zoran'], array_keys($this->grid($this->view($admin, ['user' => (string) $ben->getId()]))));
        self::assertSame(['Đorđević'], array_keys($this->grid($this->view($admin, ['user' => (string) $ana->getId()]))));

        // "samo nezavršeno" i "samo problematični" gledaju samo izabranu kategoriju
        self::assertSame([], array_keys($this->grid($this->view($admin, ['open' => '1']))), 'u Kat A je sve urađeno');
        self::assertSame([], array_keys($this->grid($this->view($admin, ['problem' => '1']))));
        $catB = (string) $this->cat('Kat B')->getId();
        self::assertSame(['Zoran'], array_keys($this->grid($this->view($admin, ['cat' => $catB, 'open' => '1']))));
        self::assertSame(['Zoran'], array_keys($this->grid($this->view($admin, ['cat' => $catB, 'problem' => '1']))));

        // izbor perioda: u aprilu nema zadataka
        $april = $this->svc()->build($this->svc()->filters(['period' => '2027-04']), $admin);
        self::assertSame('none', $this->grid($april)['Zoran']['Posao 1']);
        // brojači zaglavlja i zakasneli van perioda (svi zadaci klijenata, ne samo kategorije)
        $v = $this->view($admin, ['cat' => $catB]);
        self::assertSame(1, $v->dangerCells);
        self::assertSame(1, $v->overdueTasks);
        $aprilB = $this->svc()->build($this->svc()->filters(['period' => '2027-04', 'cat' => $catB]), $admin);
        self::assertSame(0, $aprilB->dangerCells);
        self::assertSame(1, $aprilB->overdueTasks, 'zakasneli se broje nezavisno od perioda');
    }

    public function testEmptyCategory(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $this->client('Alfa', $admin);
        $this->em->flush();
        $this->cat('Kat A');
        $v = $this->view($admin);
        self::assertTrue($v->categoryIsEmpty(), 'kategorija bez poslova');
        self::assertSame([], $v->columns);

        // posao postoji, ali ga nijedan vidljiv klijent nema
        $this->tpl('Neiskorišćen');
        self::assertTrue($this->view($admin)->categoryIsEmpty());
    }

    public function testPaging(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $t = $this->tpl('Posao');
        for ($i = 1; $i <= 5; ++$i) {
            $c = $this->client('Klijent '.$i, $admin);
            $this->em->flush();
            $this->job($admin, $c, $t, '2027-03-25');
        }
        $v = $this->view($admin, [], 2, 2);
        self::assertSame(5, $v->total);
        self::assertCount(2, $v->rows);
        self::assertTrue($v->hasMore());
        self::assertFalse($this->view($admin, [], 2, 3)->hasMore());
    }

    public function testCellDetailsMatchCellAndLimitToEightRows(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w = $this->user('Radnik');
        $c = $this->client('Alfa', $w);
        $this->em->flush();
        $t = $this->tpl('Posao');
        $other = $this->tpl('Drugi posao');
        $this->job($admin, $c, $t, '2027-03-04', TaskStatus::U_RADU, [$w]); // kasni
        $this->job($admin, $c, $t, '2027-03-08', TaskStatus::NOVO, [$w]); // uskoro
        for ($i = 0; $i < 6; ++$i) {
            $this->job($admin, $c, $t, '2027-03-25', TaskStatus::NOVO, [$w]);
        }
        for ($i = 0; $i < 3; ++$i) {
            $this->job($admin, $c, $t, '2027-03-20', TaskStatus::ZAVRSENO, [$w]);
        }
        $this->job($admin, $c, $t, '2027-03-20', TaskStatus::OTKAZANO, [$w]);
        $this->job($admin, $c, $other, '2027-03-20', TaskStatus::NOVO, [$w]); // drugi posao, ne ulazi

        $svc = $this->svc();
        $f = $svc->filters(['period' => '2027-03']);
        $cell = $svc->build($f, $w)->rows[0]['cells'][$t->getId()];
        $d = $svc->cellDetails($w, $c, $t, $f);
        self::assertSame($cell->total, $d['total']);
        self::assertSame($cell->done, $d['done']);
        self::assertSame($cell->summary, $d['summary']);
        self::assertSame($cell->state->value, $d['state']);
        self::assertSame(11, $d['total']);
        self::assertSame(['Kasni', 'Ističe uskoro', 'U toku'], array_column($d['groups'], 'label'), 'grupe redom; 8 redova je potrošeno pre "Završeno"');
        self::assertSame(8, array_sum(array_map(static fn (array $g) => \count($g['rows']), $d['groups'])), 'najviše 8 redova');
        self::assertSame(3, $d['more'], 'i još 3 zadatka');
        self::assertStringContainsString('job='.$t->getId(), $d['listUrl']);
        self::assertSame(1, $svc->cellDetails($w, $c, $other, $f)['total']);
    }

    public function testBuildQueryCountDoesNotGrowWithClients(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $t1 = $this->tpl('Posao 1');
        $t2 = $this->tpl('Posao 2');
        for ($i = 1; $i <= 30; ++$i) {
            $c = $this->client('Klijent '.$i, $admin);
            $this->em->flush();
            foreach ([$t1, $t2] as $t) {
                $this->job($admin, $c, $t, '2027-03-25');
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
        self::assertLessThanOrEqual(10, $n, 'upiti ne smeju da rastu sa brojem klijenata');
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

    /** zadatak iz posla koji pripada paketu klijenta (ili individualnog, ako je $cp null); za filter liste zadataka */
    private function packageJob(User $by, Client $client, ?ClientPackage $cp, string $due): Task
    {
        static $n = 0;
        $job = $this->clientJob($client, $this->template('PJ '.(++$n), $this->cat('Kat A')));
        $job->setClientPackage($cp);
        $t = $this->task('PJ '.$n, $by, [$by], $client, $job);
        $t->setDueDate(new \DateTimeImmutable($due));
        $this->em->flush();

        return $t;
    }

    private function manual(User $by, Client $client, string $due): Task
    {
        $t = $this->task('Ručni '.$due, $by, [$by], $client, null);
        $t->setDueDate(new \DateTimeImmutable($due));
        $this->em->flush();

        return $t;
    }

    public function testTaskListPackageFilterAndNoPackage(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $p1 = $this->package('Paket 1');
        $c = $this->client('Alfa', $admin);
        $this->em->flush();
        $cp = $this->give($c, $p1);
        $this->em->flush();
        $inPackage = $this->packageJob($admin, $c, $cp, '2027-03-25');
        $individual = $this->packageJob($admin, $c, null, '2027-03-25');
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
