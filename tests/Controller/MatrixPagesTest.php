<?php

namespace App\Tests\Controller;

use App\Entity\Client;
use App\Entity\ClientPackage;
use App\Entity\ServicePackage;
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

    /** @return array{0: User, 1: User, 2: Client, 3: Client, 4: ServicePackage} radnik, kolega, klijent radnika, klijent kolege, paket */
    private function setUpData(string $month): array
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w = $this->user('Radnik');
        $other = $this->user('Kolega');
        $pkg = (new ServicePackage())->setName('Paket 1');
        $this->em->persist($pkg);
        $cat = $this->category('Kat');
        $mine = $this->client('Moj', $w);
        $theirs = $this->client('Tudj', $other);
        $this->em->flush();
        foreach ([[$mine, $w], [$theirs, $other]] as [$client, $worker]) {
            $cp = (new ClientPackage())->setClient($client)->setPackage($pkg)->setAgreedPrice('100.00')->setStartDate(new \DateTimeImmutable('2026-01-01'));
            $this->em->persist($cp);
            $job = $this->clientJob($client, $this->template('Posao '.$client->getShortName(), $cat))->setClientPackage($cp);
            $this->em->flush();
            foreach ([TaskStatus::ZAVRSENO, TaskStatus::NOVO] as $i => $status) {
                $t = $this->task('Zadatak '.$client->getShortName().$i, $admin, [$worker], $client, $job, $status);
                $t->setDueDate(new \DateTimeImmutable($month.'-2'.$i));
            }
        }
        $this->em->flush();

        return [$w, $other, $mine, $theirs, $pkg];
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
        self::assertStringContainsString('1/2', $html);
        // blok je posle kartica Zadaci
        self::assertGreaterThan(strpos($html, 'Klijenti (backup)'), strpos($html, 'Pregled poslova po klijentima'));

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
    }

    public function testCellDetailsMatchCellAndRespectVisibility(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w, , $mine, $theirs, $pkg] = $this->setUpData($month);
        $this->login($w);

        $res = $this->get(sprintf('/ajax/matrix/cell?client=%d&package=%d&period=%s', $mine->getId(), $pkg->getId(), $month));
        self::assertTrue($res['success']);
        self::assertSame(2, $res['data']['total']);
        self::assertSame(1, $res['data']['done']);
        self::assertSame('1 od 2 gotovo', explode(',', $res['data']['summary'])[0]);
        self::assertSame('Moj', $res['data']['clientName']);
        self::assertStringContainsString('package='.$pkg->getId(), $res['data']['listUrl']);

        // "Ostali taskovi" nemaju zadataka iz paketa
        $other = $this->get(sprintf('/ajax/matrix/cell?client=%d&package=none&period=%s', $mine->getId(), $month));
        self::assertSame(0, $other['data']['total']);

        // tuđ klijent i nepoznat paket: 404
        $this->get(sprintf('/ajax/matrix/cell?client=%d&package=%d&period=%s', $theirs->getId(), $pkg->getId(), $month));
        self::assertResponseStatusCodeSame(404);
        $this->get(sprintf('/ajax/matrix/cell?client=%d&package=999999&period=%s', $mine->getId(), $month));
        self::assertResponseStatusCodeSame(404);
        $this->get(sprintf('/ajax/matrix/cell?client=%d&package=abc', $mine->getId()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testOpeningCellRunsOneTaskQuery(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w, , $mine, , $pkg] = $this->setUpData($month);
        $this->login($w);
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $this->get(sprintf('/ajax/matrix/cell?client=%d&package=%d&period=%s', $mine->getId(), $pkg->getId(), $month));
        $taskQueries = array_filter($holder->getData()['default'] ?? [], static fn (array $q) => str_contains($q['sql'], 'FROM task '));
        self::assertCount(1, $taskQueries, 'prozor ćelije učitava zadatke jednim upitom');
    }

    public function testExportUsesSameFiltersAndVisibility(): void
    {
        $month = (new \DateTimeImmutable('today'))->format('Y-m');
        [$w] = $this->setUpData($month);
        $this->login($w);
        $export = self::getContainer()->get(\App\Export\MatrixExport::class);

        $rows = iterator_to_array($export->rows(['period' => $month], $w), false);
        self::assertCount(1, $rows, 'jedna ćelija sa zadacima: klijent radnika x Paket 1');
        self::assertSame('Moj', $rows[0][0]);
        self::assertStringContainsString('Radnik', (string) $rows[0][1]);
        self::assertSame('Paket 1', $rows[0][3]);
        self::assertSame([2, 1], [$rows[0][5], $rows[0][6]]);
        self::assertSame(1, $export->count(['period' => $month], $w));
        self::assertSame([], iterator_to_array($export->rows(['period' => $month, 'q' => 'nepostojeci'], $w), false));

        $this->browser->request('GET', '/export/pregled/csv?period='.$month);
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/pregled-poslova');
        self::assertStringContainsString('/export/pregled/csv', (string) $this->browser->getResponse()->getContent());
        self::assertStringContainsString('Pregled poslova</', (string) $this->browser->getResponse()->getContent());
    }
}
