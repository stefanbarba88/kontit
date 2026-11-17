<?php

namespace App\Tests\Controller;

use App\Entity\Client;
use App\Entity\JobTemplate;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Tests\Support\DatabaseTestCase;

/** Matrica "Pregled poslova po klijentima": početna, AJAX osvežavanje i prozor ćelije (SPEC 8r). */
class MatrixPagesTest extends DatabaseTestCase
{
    private string $csrf = '';

    private function login(User $user): void
    {
        $this->browser->loginUser($user);
        $crawler = $this->browser->request('GET', '/pregled-poslova');
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
    }

    /** @return array<string, mixed> */
    private function get(string $url): array
    {
        $this->browser->request('GET', $url, [], [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);

        return json_decode((string) $this->browser->getResponse()->getContent(), true) ?? [];
    }

    /** @return array{0: User, 1: User, 2: Client, 3: Client, 4: JobTemplate} radnik, kolega, klijent radnika, klijent kolege, posao */
    private function setUpData(string $month): array
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w = $this->user('Radnik');
        $other = $this->user('Kolega');
        $cat = $this->category('Kat')->setPosition(-100);
        $tpl = $this->template('Posao 1', $cat);
        $mine = $this->client('Moj', $w);
        $theirs = $this->client('Tudj', $other);
        $this->em->flush();
        foreach ([[$mine, $w], [$theirs, $other]] as [$client, $worker]) {
            $job = $this->clientJob($client, $tpl);
            $this->em->flush();
            foreach ([TaskStatus::ZAVRSENO, TaskStatus::NOVO] as $i => $status) {
                $t = $this->task('Zadatak '.$client->getShortName().$i, $admin, [$worker], $client, $job, $status);
                $t->setDueDate(new \DateTimeImmutable($month.'-2'.$i));
            }
        }
        $this->em->flush();

        return [$w, $other, $mine, $theirs, $tpl];
    }

    public function testDashboardAndPageShowMatrixWithOwnClientsOnly(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w] = $this->setUpData($month);
        $this->browser->loginUser($w);

        $this->browser->request('GET', '/');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Pregled poslova po klijentima', $html);
        self::assertStringContainsString('Otvori celu stranicu', $html);
        self::assertStringContainsString('>Moj</a>', $html);
        self::assertStringNotContainsString('>Tudj</a>', $html, 'radnik ne vidi tuđe klijente');
        self::assertStringContainsString('name="cat"', $html, 'select Kategorija poslova');
        self::assertStringNotContainsString('name="pkg"', $html);
        self::assertStringContainsString('>Tim</th>', $html);
        self::assertStringNotContainsString('Ostali taskovi', $html);
        self::assertStringContainsString('title="Posao 1"', $html, 'kolona po poslu');
        self::assertStringContainsString('kt-mx-status', $html, 'ćelija je samo ikonica statusa');
        self::assertStringNotContainsString('data-kt-mx-cell', $html, 'ćelija nema klik');
        foreach (['Samo nezavršeno', 'Samo problematični', 'Legenda', 'zakasnelih zadataka ukupno', 'name="open"', 'name="problem"'] as $removed) {
            self::assertStringNotContainsString($removed, $html, $removed.' je uklonjeno');
        }
        self::assertStringContainsString('Poništi filtere', $html);
        self::assertStringContainsString('aria-label="Posao 1 · Moj: ', $html, 'status ima aria-label');
        // blok je posle kartica Zadaci
        self::assertGreaterThan(strpos($html, 'Završeno ovog meseca'), strpos($html, 'Pregled poslova po klijentima'));

        $this->browser->request('GET', '/pregled-poslova');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('data-full="1"', (string) $this->browser->getResponse()->getContent());
    }

    public function testAjaxTableAndFilters(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w] = $this->setUpData($month);
        $this->login($w);

        $res = $this->get('/ajax/matrix?page=1&full=0&period='.$month);
        self::assertTrue($res['success']);
        self::assertSame(1, $res['data']['total']);
        self::assertStringContainsString('Moj', $res['data']['body']);
        self::assertStringNotContainsString('Tudj', $res['data']['body']);
        $none = $this->get('/ajax/matrix?page=1&q=nepostojeci');
        self::assertSame(0, $none['data']['total']);
        self::assertStringContainsString('Nema klijenata za izabrane filtere', $none['data']['body']);
        $more = $this->get('/ajax/matrix?page=2&period='.$month);
        self::assertArrayHasKey('rows', $more['data'], 'sledeće strane vraćaju samo redove');

        // neispravni parametri ne daju 500: kategorija, strana, period, full, zaposleni
        foreach (['cat=abc', 'cat=', 'cat=999999', 'page=', 'page=abc', 'period=xx', 'full=zzz', 'user=abc'] as $bad) {
            $r = $this->get('/ajax/matrix?'.$bad);
            self::assertResponseIsSuccessful($bad);
            self::assertTrue($r['success'], $bad);
        }
    }

    public function testCategoryWithoutJobsShowsEmptyState(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w] = $this->setUpData($month);
        $empty = $this->category('Prazna')->setPosition(-50);
        $this->em->flush();
        $this->login($w);

        $res = $this->get('/ajax/matrix?page=1&cat='.$empty->getId().'&period='.$month);
        self::assertTrue($res['success']);
        self::assertStringContainsString('Nema poslova u ovoj kategoriji', $res['data']['body']);
        self::assertStringNotContainsString('<table', $res['data']['body']);
    }

    public function testCellClickEndpointIsGone(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w, , $mine, , $tpl] = $this->setUpData($month);
        $this->login($w);
        $this->get(sprintf('/ajax/matrix/cell?client=%d&job=%d&period=%s', $mine->getId(), $tpl->getId(), $month));
        self::assertResponseStatusCodeSame(404);
    }

    public function testExportFollowsSelectedCategoryWithStatusText(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w] = $this->setUpData($month);
        $this->login($w);
        $export = self::getContainer()->get(\App\Export\MatrixExport::class);

        $labels = array_column($export->getColumns(['period' => $month], $w), 'label');
        self::assertSame(['Klijent', 'Tim (glavni)', 'Backup', 'Posao 1'], $labels, 'kolone izabrane kategorije');
        $rows = iterator_to_array($export->rows(['period' => $month], $w), false);
        self::assertCount(1, $rows);
        self::assertSame('Moj', $rows[0][0]);
        self::assertStringContainsString('Radnik', (string) $rows[0][1]);
        self::assertContains($rows[0][3], ['Kasni', 'U toku'], 'jedan zadatak je završen, drugi nije');
        self::assertSame(1, $export->count(['period' => $month], $w));
        self::assertSame([], iterator_to_array($export->rows(['period' => $month, 'q' => 'nepostojeci'], $w), false));
        $described = $export->describeFilters(['period' => $month, 'open' => '1', 'problem' => '1'], $w);
        self::assertStringContainsString('Kategorija: Kat', $described);
        self::assertStringNotContainsString('nezavršeno', $described);
        self::assertStringNotContainsString('problematični', $described);

        $this->browser->request('GET', '/export/pregled/csv?period='.$month);
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/pregled-poslova');
        self::assertStringContainsString('/export/pregled/csv', (string) $this->browser->getResponse()->getContent());
        self::assertStringContainsString('Pregled poslova</', (string) $this->browser->getResponse()->getContent());
    }
}
