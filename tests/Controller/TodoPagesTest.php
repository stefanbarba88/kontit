<?php

namespace App\Tests\Controller;

use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\Role;
use App\Service\TodoService;
use App\Tests\Support\DatabaseTestCase;

/** Moja lista kroz HTTP: strana, AJAX API i privatnost (tuđa stavka je 404 i za admina i za superadmina). */
class TodoPagesTest extends DatabaseTestCase
{
    private string $csrf = '';

    /** prijava + CSRF token iz <meta> (isti kao u JS-u) */
    private function login(User $user): void
    {
        $this->browser->loginUser($user);
        $crawler = $this->browser->request('GET', '/moja-lista');
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
    }

    /** @return array<string, mixed> */
    private function api(string $method, string $url, array $payload = []): array
    {
        $this->browser->request($method, $url, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->csrf], 'GET' === $method ? null : json_encode($payload));

        return json_decode((string) $this->browser->getResponse()->getContent(), true) ?? [];
    }

    public function testPageAndPanelRenderAndAddCompleteUndoDelete(): void
    {
        $me = $this->user('Ja');
        $this->em->flush();
        $this->login($me);
        self::assertResponseIsSuccessful();
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('data-kt-todo-panel', $html, 'panel je na svakoj strani');
        self::assertStringContainsString('Dodaj u moju listu', $html);

        $res = $this->api('POST', '/ajax/todo', ['title' => 'Pozvati klijenta', 'dueDate' => '2026-12-01', 'dueTime' => '09:00', 'remindDate' => '2026-12-01', 'remindTime' => '09:00']);
        self::assertTrue($res['success']);
        self::assertStringContainsString('Pozvati klijenta', $res['data']['html']);
        self::assertTrue($res['data']['hasReminder']);
        $id = $res['data']['id'];

        // prazan naslov i neispravan datum
        self::assertFalse($this->api('POST', '/ajax/todo', ['title' => '  '])['success']);
        self::assertFalse($this->api('POST', '/ajax/todo', ['title' => 'X', 'dueDate' => '2026-13-45'])['success']);

        // izmena + zakačeno
        $res = $this->api('POST', '/ajax/todo/'.$id, ['note' => 'Napomena', 'pinned' => true]);
        self::assertTrue($res['success']);
        self::assertStringContainsString('is-pinned', $res['data']['html']);

        // završi pa poništi
        $res = $this->api('POST', '/ajax/todo/'.$id.'/done');
        self::assertTrue($res['success']);
        $this->em->clear();
        self::assertTrue($this->em->find(TodoItem::class, $id)->isDone());
        $this->api('POST', '/ajax/todo/'.$id.'/reopen');
        $this->em->clear();
        self::assertFalse($this->em->find(TodoItem::class, $id)->isDone());

        // brisanje vraća snimak, "Poništi" ga vraća
        $res = $this->api('DELETE', '/ajax/todo/'.$id);
        self::assertTrue($res['success']);
        $snapshot = $res['data']['snapshot'];
        self::assertSame('Pozvati klijenta', $snapshot['title']);
        $this->em->clear();
        self::assertNull($this->em->find(TodoItem::class, $id));
        $res = $this->api('POST', '/ajax/todo/restore', $snapshot);
        self::assertTrue($res['success']);
        self::assertStringContainsString('Pozvati klijenta', $res['data']['html']);
    }

    public function testReorderAndSearchOnlyOwnItems(): void
    {
        $me = $this->user('Ja');
        $other = $this->user('Drugi');
        $this->em->flush();
        $todo = self::getContainer()->get(TodoService::class);
        $a = $todo->add($me, 'Prva stavka');
        $b = $todo->add($me, 'Druga stavka');
        $todo->add($other, 'Tudja stavka tajna');

        $this->login($me);
        $this->api('POST', '/ajax/todo/reorder', ['ids' => [$b->getId(), $a->getId(), $todo->add($other, 'x')->getId()]]);
        $this->em->clear();
        self::assertLessThan($this->em->find(TodoItem::class, $a->getId())->getPosition(), $this->em->find(TodoItem::class, $b->getId())->getPosition());

        $res = $this->api('GET', '/ajax/todo?q=stavka');
        self::assertStringContainsString('Prva stavka', $res['data']['html']);
        self::assertStringNotContainsString('Tudja', $res['data']['html']);
        $this->browser->request('GET', '/moja-lista?q=tajna');
        self::assertStringNotContainsString('Tudja stavka', (string) $this->browser->getResponse()->getContent());
    }

    /** dopuna D: admin i superadmin dobijaju 404 na svaku akciju nad tuđom stavkom */
    public function testOthersItemIs404ForAdminAndSuperadmin(): void
    {
        $owner = $this->user('Vlasnik');
        $admin = $this->user('Admin', Role::ADMIN);
        $super = $this->user('Super', Role::SUPERADMIN);
        $this->em->flush();
        $item = self::getContainer()->get(TodoService::class)->add($owner, 'Privatna');
        $id = $item->getId();

        foreach ([$admin, $super] as $intruder) {
            $this->login($intruder);
            foreach ([['POST', "/ajax/todo/$id"], ['POST', "/ajax/todo/$id/done"], ['POST', "/ajax/todo/$id/reopen"], ['DELETE', "/ajax/todo/$id"]] as [$method, $url]) {
                $this->api($method, $url, ['title' => 'hak']);
                self::assertResponseStatusCodeSame(404, "$method $url");
            }
            $res = $this->api('GET', '/ajax/todo');
            self::assertStringNotContainsString('Privatna', $res['data']['html']);
            $this->browser->request('GET', '/moja-lista');
            self::assertStringNotContainsString('Privatna', (string) $this->browser->getResponse()->getContent());
        }
        $this->em->clear();
        $fresh = $this->em->find(TodoItem::class, $id);
        self::assertSame('Privatna', $fresh->getTitle());
        self::assertFalse($fresh->isDone());
    }

    /** dopuna D: /ajax/todo/due vraća samo stavke ulogovanog i svaki podsetnik preuzima tačno jedan poziv */
    public function testDueEndpointReturnsOnlyOwnReminderOnce(): void
    {
        $me = $this->user('Ja');
        $other = $this->user('Drugi');
        $this->em->flush();
        $todo = self::getContainer()->get(TodoService::class);
        $clock = self::getContainer()->get(\App\Service\TodoClock::class);
        $mine = $todo->add($me, "Moj podsetnik");
        $theirs = $todo->add($other, "Tudji podsetnik");
        foreach ([$mine, $theirs] as $item) {
            $item->setRemindAt($clock->now()->modify("-5 minutes"));
            $todo->save($item);
        }
        self::getContainer()->get(\App\Service\TodoReminderService::class)->sendDue();

        $this->login($me);
        $res = $this->api("GET", "/ajax/todo/due");
        self::assertSame(["Moj podsetnik"], array_column($res["data"]["reminders"], "title"));
        $again = $this->api("GET", "/ajax/todo/due");
        self::assertSame([], $again["data"]["reminders"], "drugi tab ne dobija isti toast");

        // odlaganje tuđe stavke: 404
        $this->api("POST", "/ajax/todo/".$theirs->getId()."/snooze", ["preset" => "10m"]);
        self::assertResponseStatusCodeSame(404);
        $res = $this->api("POST", "/ajax/todo/".$mine->getId()."/snooze", ["preset" => "1h"]);
        self::assertTrue($res["success"]);

        // zvono: tip ima svoju ikonu i tekst, a u podešavanjima profila ga nema (mail se bira na stavci)
        $this->browser->request("GET", "/ajax/notifications");
        $menu = json_decode((string) $this->browser->getResponse()->getContent(), true)["data"]["html"];
        self::assertStringContainsString("Podsetnik: Moj podsetnik", $menu);
        self::assertStringContainsString("kt-tone-primary", $menu);
        self::assertStringContainsString("<svg", $menu);
        $this->browser->request("GET", "/profile");
        self::assertStringNotContainsString("Podsetnik iz Moje liste", (string) $this->browser->getResponse()->getContent());
    }
}