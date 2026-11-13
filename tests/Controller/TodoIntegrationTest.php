<?php

namespace App\Tests\Controller;

use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\Role;
use App\Service\TodoClock;
use App\Service\TodoService;
use App\Tests\Support\DatabaseTestCase;

/** Podsetnik u ostatku aplikacije: "Dodaj u podsetnik", "Pretvori u zadatak", kalendar, dashboard i veze bez prava. */
class TodoIntegrationTest extends DatabaseTestCase
{
    private string $csrf = '';

    private function login(User $user): void
    {
        $this->browser->loginUser($user);
        $crawler = $this->browser->request('GET', '/podsetnik');
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
    }

    /** @return array<string, mixed> */
    private function api(string $method, string $url, array $payload = []): array
    {
        $this->browser->request($method, $url, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->csrf], 'GET' === $method ? null : json_encode($payload));

        return json_decode((string) $this->browser->getResponse()->getContent(), true) ?? [];
    }

    private function todo(): TodoService
    {
        return self::getContainer()->get(TodoService::class);
    }

    public function testRemindMeCreatesLinkedItemAndRespectsVisibility(): void
    {
        $me = $this->user('Radnik');
        $stranger = $this->user('Strani');
        $client = $this->client('Firma X', $me);
        $own = $this->task('Mesečni PDV', $me, [$me], $client);
        $foreign = $this->task('Tuđi zadatak', $stranger, [$stranger]);
        $this->em->flush();

        $this->login($me);
        $res = $this->api('POST', '/ajax/todo/remind', ['taskId' => $own->getId(), 'title' => 'Mesečni PDV — Firma X', 'date' => '2026-12-01', 'time' => '09:00']);
        self::assertTrue($res['success']);
        self::assertStringContainsString('Mesečni PDV — Firma X', $res['data']['html']);
        $item = $this->em->getRepository(TodoItem::class)->findOneBy(['owner' => $me]);
        self::assertSame($own->getId(), $item->getTask()->getId());
        self::assertSame($client->getId(), $item->getClient()->getId());
        self::assertNotNull($item->getRemindAt());

        // nema datuma: greška; nema prava na zadatak: 404
        self::assertFalse($this->api('POST', '/ajax/todo/remind', ['clientId' => $client->getId(), 'title' => 'X'])['success']);
        $this->api('POST', '/ajax/todo/remind', ['taskId' => $foreign->getId(), 'title' => 'X', 'date' => '2026-12-01']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, $this->em->getRepository(TodoItem::class)->count(['owner' => $me]), 'neuspeli pokušaji ne ostavljaju stavke');

        // veza na klijenta radi i bez zadatka; link se vidi samo uz pravo
        $this->api('POST', '/ajax/todo/remind', ['clientId' => $client->getId(), 'title' => 'Pozvati', 'date' => '2026-12-02']);
        $this->browser->request('GET', '/podsetnik');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('href="/tasks/'.$own->getId().'"', $html);
    }

    public function testLinkBecomesPlainTextWhenOwnerCannotSeeTaskAnymore(): void
    {
        $me = $this->user('Radnik');
        $other = $this->user('Drugi');
        $task = $this->task('Skriveni zadatak', $other, [$other]);
        $this->em->flush();
        $item = $this->todo()->add($me, 'Veza bez prava');
        $item->setTask($task);
        $this->todo()->save($item);

        $this->login($me);
        $this->browser->request('GET', '/podsetnik');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Skriveni zadatak', $html, 'naslov je tekst');
        self::assertStringNotContainsString('href="/tasks/'.$task->getId().'"', $html, 'ali bez linka');
    }

    /** dopuna D: ?fromTodo=ID samo za vlasnika */
    public function testConvertToTaskPrefillsAndIsPrivate(): void
    {
        $owner = $this->user('Vlasnik', Role::ADMIN);
        $admin = $this->user('Admin', Role::ADMIN);
        $super = $this->user('Super', Role::SUPERADMIN);
        $this->em->flush();
        $item = $this->todo()->add($owner, 'Pozvati klijenta zbog PDV-a');
        $this->todo()->fill($item, ['note' => 'Dogovoriti rok', 'dueDate' => '2026-12-05']);
        $this->todo()->save($item);

        $this->browser->loginUser($owner);
        $crawler = $this->browser->request('GET', '/tasks/new?fromTodo='.$item->getId());
        self::assertResponseIsSuccessful();
        self::assertSame('Pozvati klijenta zbog PDV-a', $crawler->filter('input[name="task[title]"]')->attr('value'));
        self::assertSame('2026-12-05', $crawler->filter('input[name="task[dueDate]"]')->attr('value'));

        foreach ([$admin, $super] as $intruder) {
            $this->browser->loginUser($intruder);
            $this->browser->request('GET', '/tasks/new?fromTodo='.$item->getId());
            self::assertResponseStatusCodeSame(404);
        }

        // posle čuvanja zadatka stavka je završena i vezana za zadatak
        $owner = $this->em->find(User::class, $owner->getId());
        $item = $this->em->find(TodoItem::class, $item->getId());
        $task = $this->task('Novi zadatak', $owner, [$owner]);
        $this->em->flush();
        $this->todo()->markConverted($item, $task);
        $this->em->clear();
        $fresh = $this->em->find(TodoItem::class, $item->getId());
        self::assertTrue($fresh->isDone());
        self::assertSame($task->getId(), $fresh->getCreatedTask()->getId());
    }

    public function testCalendarShowsOnlyOwnTodoItems(): void
    {
        $owner = $this->user('Vlasnik');
        $admin = $this->user('Admin', Role::ADMIN);
        $this->em->flush();
        $clock = self::getContainer()->get(TodoClock::class);
        $item = $this->todo()->add($owner, 'Moj datum');
        $this->todo()->fill($item, ['dueDate' => '2026-12-10', 'dueTime' => '14:30']);
        $this->todo()->save($item);
        $secret = $this->todo()->add($admin, 'Adminova tajna');
        $this->todo()->fill($secret, ['dueDate' => '2026-12-10']);
        $this->todo()->save($secret);

        $url = '/ajax/calendar/events?start=2026-12-01&end=2027-01-01&types=todo&scope=';
        $this->browser->loginUser($owner);
        $this->browser->request('GET', $url.'mine');
        $events = json_decode((string) $this->browser->getResponse()->getContent(), true);
        self::assertSame(['Moj datum'], array_column($events, 'title'));
        self::assertSame('2026-12-10T14:30:00', $events[0]['start']);
        self::assertFalse($events[0]['allDay']);

        // admin vidi samo svoje, i sa opsegom "svi"
        $this->browser->loginUser($admin);
        $this->browser->request('GET', $url.'all');
        self::assertSame(['Adminova tajna'], array_column(json_decode((string) $this->browser->getResponse()->getContent(), true), 'title'));
        // bez tipa "todo" stavke se ne mešaju u ostale događaje
        $this->browser->request('GET', '/ajax/calendar/events?start=2026-12-01&end=2027-01-01&types=tasks&scope=all');
        self::assertSame([], json_decode((string) $this->browser->getResponse()->getContent(), true));
        self::assertNotNull($clock);
    }

    public function testDashboardHasNoPodsetnikCardAndTopbarIconIsOnlySignal(): void
    {
        $me = $this->user('Ja');
        $other = $this->user('Drugi');
        $this->em->flush();
        $clock = self::getContainer()->get(TodoClock::class);
        foreach ([[$me, 'Moja danasnja'], [$other, 'Tudja danasnja']] as [$owner, $title]) {
            $item = $this->todo()->add($owner, $title);
            $this->todo()->fill($item, ['dueDate' => $clock->today()]);
            $this->todo()->save($item);
        }

        $this->browser->loginUser($me);
        $this->browser->request('GET', '/');
        $html = (string) $this->browser->getResponse()->getContent();
        // nema kartice Podsetnik na početnoj; stavke se vide samo u panelu koji se učitava AJAX-om
        self::assertStringNotContainsString('kt-todo-card', $html);
        self::assertStringNotContainsString('Moja danasnja', $html);
        self::assertStringNotContainsString('Tudja danasnja', $html);
        // jedna stavka "Podsetnik" u meniju (dugme koje otvara panel), bez linka na stranicu u meniju
        self::assertSame(1, substr_count($html, 'class="kt-nav-link  "') + substr_count($html, 'class="kt-nav-link is-active"') - substr_count($html, 'href="/podsetnik" class="kt-nav-link'));
        self::assertSame(1, preg_match_all('/<button[^>]*kt-nav-link[^>]*data-kt-open-todo-panel/', $html));
        self::assertStringNotContainsString('href="/podsetnik" class="kt-nav-link', $html);
        self::assertStringContainsString('Otvori celu stranicu', $html);
        // signal: jedna stavka za danas = crveni broj 1, bez tačke
        self::assertMatchesRegularExpression('/kt-todo-dot todo-dot d-none/', $html);
        self::assertMatchesRegularExpression('/kt-todo-badge todo-count\s*">1</', $html);
    }

    public function testTopbarSignalDotWhenOnlyFutureItemsAndNothingWhenEmpty(): void
    {
        $me = $this->user('Ja');
        $empty = $this->user('Prazan');
        $this->em->flush();
        $clock = self::getContainer()->get(TodoClock::class);
        $later = $this->todo()->add($me, 'Za mesec dana');
        $this->todo()->fill($later, ['dueDate' => (new \DateTimeImmutable($clock->today().' +30 days'))->format('Y-m-d')]);
        $this->todo()->save($later);

        $this->browser->loginUser($me);
        $this->browser->request('GET', '/');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertMatchesRegularExpression('/kt-todo-dot todo-dot\s*">/', $html, 'tačka kad ima otvorenih, a nema zakasnelih ni današnjih');
        self::assertMatchesRegularExpression('/kt-todo-badge todo-count d-none"/', $html);

        $this->browser->loginUser($empty);
        $this->browser->request('GET', '/');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertMatchesRegularExpression('/kt-todo-dot todo-dot d-none/', $html);
        self::assertMatchesRegularExpression('/kt-todo-badge todo-count d-none"/', $html);
    }
}
