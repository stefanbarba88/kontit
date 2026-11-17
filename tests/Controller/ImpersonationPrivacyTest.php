<?php

namespace App\Tests\Controller;

use App\Entity\Notification;
use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\Role;
use App\Security\Voter\TodoVoter;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Privatnost za vreme "Prikaži kao korisnik" (SPEC 8s): Podsetnik ne postoji (404, nema ga u meniju, signalu i panelu),
 * obaveštenja iz Podsetnika se kriju, a u režimu "samo pregled" se ništa ne označava kao pročitano. Po izlasku sve radi.
 */
class ImpersonationPrivacyTest extends DatabaseTestCase
{
    private User $admin;
    private User $worker;
    private TodoItem $item;
    private Notification $todoNote;
    private Notification $taskNote;
    private string $token = '';
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('Admin', Role::ADMIN);
        $this->worker = $this->user('Radnik');
        $this->item = (new TodoItem($this->worker, 'TAJNA STAVKA'))->setRemindAt(new \DateTimeImmutable('-1 minute'));
        $this->em->persist($this->item);
        $this->todoNote = new Notification($this->worker, NotificationType::TODO_REMINDER, 'TAJNO OBAVESTENJE', 'Privatno', '/podsetnik');
        $this->taskNote = new Notification($this->worker, NotificationType::TASK_ASSIGNED, 'Obicno obavestenje', 'Dodeljen zadatak', '/tasks');
        $this->em->persist($this->todoNote);
        $this->em->persist($this->taskNote);
        $this->em->flush();
    }

    private function startImpersonation(): void
    {
        $this->browser->loginUser($this->admin);
        $crawler = $this->browser->request('GET', '/admin/users/'.$this->worker->getId());
        $this->token = (string) $crawler->filter('input[name=_switch_user]')->closest('form')->filter('input[name=_token]')->attr('value');
        $this->browser->request('POST', '/impersonation/'.$this->worker->getId(), ['_switch_user' => $this->worker->getEmail(), '_token' => $this->token]);
        $crawler = $this->browser->followRedirect();
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
    }

    private function editMode(): void
    {
        $crawler = $this->browser->request('GET', '/');
        $modeToken = (string) $crawler->filter('form[action="/impersonation/mode"] input[name=_token]')->attr('value');
        $this->browser->request('POST', '/impersonation/mode', ['_token' => $modeToken, 'mode' => 'edit']);
    }

    private function exitImpersonation(): void
    {
        $this->browser->request('POST', '/impersonation/'.$this->worker->getId(), ['_switch_user' => '_exit', '_token' => $this->token]);
    }

    private function body(): string
    {
        return (string) $this->browser->getResponse()->getContent();
    }

    private function ajax(string $method, string $url, array $payload = []): void
    {
        $this->browser->request($method, $url, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->csrf], 'GET' === $method ? null : json_encode($payload));
    }

    /** @return iterable<string, array{string, string}> */
    public static function todoRoutes(): iterable
    {
        yield 'strana' => ['GET', '/podsetnik'];
        yield 'stara adresa' => ['GET', '/moja-lista'];
        yield 'lista' => ['GET', '/ajax/todo'];
        yield 'polling' => ['GET', '/ajax/todo/due'];
        yield 'klijenti' => ['GET', '/ajax/todo/clients'];
        yield 'dodavanje' => ['POST', '/ajax/todo'];
        yield 'izmena' => ['POST', '/ajax/todo/1'];
        yield 'završi' => ['POST', '/ajax/todo/1/done'];
        yield 'odloži' => ['POST', '/ajax/todo/1/snooze'];
        yield 'redosled' => ['POST', '/ajax/todo/reorder'];
        yield 'brisanje' => ['DELETE', '/ajax/todo/1'];
        yield 'dodaj u podsetnik' => ['POST', '/ajax/todo/remind'];
    }

    /** U obe varijante režima Podsetnik je 404 (u "samo pregled" i u režimu izmena). */
    #[\PHPUnit\Framework\Attributes\DataProvider('todoRoutes')]
    public function testEveryTodoRouteIsNotFoundWhileImpersonating(string $method, string $url): void
    {
        $this->startImpersonation();
        $this->ajax($method, $url, ['title' => 'x']);
        self::assertResponseStatusCodeSame(404, "$method $url u režimu samo pregled");
        $this->editMode();
        $this->ajax($method, $url, ['title' => 'x']);
        self::assertResponseStatusCodeSame(404, "$method $url u režimu izmena");
    }

    public function testPollingDoesNotMarkRemindersAndNothingIsLeaked(): void
    {
        $this->startImpersonation();
        $this->ajax('GET', '/ajax/todo/due');
        self::assertResponseStatusCodeSame(404);
        self::assertStringNotContainsString('TAJNA STAVKA', $this->body());
        $this->em->clear();
        $item = $this->em->find(TodoItem::class, $this->item->getId());
        self::assertNull($item->getShownAt(), 'polling ne markira podsetnik kao prikazan');
        self::assertSame('TAJNA STAVKA', $item->getTitle());
    }

    public function testMenuSignalPanelAndRemindButtonsAreGone(): void
    {
        $this->startImpersonation();
        foreach (['/', '/tasks', '/calendar', '/profile', '/notifications'] as $url) {
            $this->browser->request('GET', $url);
            $html = $this->body();
            self::assertStringNotContainsString('kt-todo-btn', $html, "signal u topbaru na $url");
            self::assertStringNotContainsString('id="kt-todo-panel"', $html, "panel na $url");
            self::assertStringNotContainsString('data-kt-open-todo-panel', $html, "stavka menija na $url");
            self::assertStringNotContainsString('TAJNA STAVKA', $html);
            self::assertStringNotContainsString('TAJNO OBAVESTENJE', $html);
            self::assertStringNotContainsString('kt-todo-card', $html, "kartica Podsetnik na $url");
        }
        $this->browser->request('GET', '/');
        self::assertStringNotContainsString('has-todo', $this->body(), 'Zadaci su preko cele širine bez kartice Podsetnik');
        $this->browser->request('GET', '/calendar');
        self::assertStringNotContainsString('cal-type-todo', $this->body(), 'tip Podsetnik u kalendaru');
        self::assertStringContainsString('cal-type-tasks', $this->body());
    }

    public function testBellShowsOrdinaryNotificationsButHidesTodoReminders(): void
    {
        $this->startImpersonation();
        $this->browser->request('GET', '/');
        self::assertStringContainsString('Obicno obavestenje', $this->body(), 'obaveštenja se prikazuju');
        self::assertStringNotContainsString('TAJNO OBAVESTENJE', $this->body());
        $this->ajax('GET', '/ajax/notifications');
        $data = json_decode($this->body(), true)['data'];
        self::assertSame(1, $data['count'], 'brojač ne računa obaveštenje iz Podsetnika');
        self::assertStringNotContainsString('TAJNO', $data['html']);
        $this->browser->request('GET', '/notifications');
        self::assertStringNotContainsString('TAJNO OBAVESTENJE', $this->body());
        $this->browser->request('GET', '/notifications/'.$this->todoNote->getId().'/open');
        self::assertResponseStatusCodeSame(404);
    }

    public function testNotificationsAreNotMarkedReadInViewModeButAreInEditMode(): void
    {
        $this->startImpersonation();
        $this->browser->request('GET', '/notifications/'.$this->taskNote->getId().'/open');
        self::assertResponseRedirects();
        $this->ajax('POST', '/ajax/notifications/'.$this->taskNote->getId().'/read');
        self::assertResponseStatusCodeSame(403, 'označavanje je izmena, blokirano u samo pregled');
        $this->ajax('POST', '/ajax/notifications/read-all');
        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertFalse($this->em->find(Notification::class, $this->taskNote->getId())->isRead(), 'ostaje nepročitano');

        $this->editMode();
        $this->browser->request('GET', '/notifications/'.$this->taskNote->getId().'/open');
        $this->em->clear();
        self::assertTrue($this->em->find(Notification::class, $this->taskNote->getId())->isRead(), 'u režimu izmena radi u ime korisnika');
    }

    public function testEverythingWorksAgainAfterExit(): void
    {
        $this->startImpersonation();
        $this->ajax('GET', '/ajax/todo/due');
        self::assertResponseStatusCodeSame(404);
        $this->exitImpersonation();
        self::assertResponseRedirects();

        // admin (stvarni nalog) ima svoj Podsetnik; sada mu radi sve
        $this->browser->request('GET', '/podsetnik');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('id="kt-todo-panel"', $this->body());
        $this->ajax('GET', '/ajax/todo/due');
        self::assertResponseIsSuccessful();
        // i radnik, kad se on prijavi, vidi svoju stavku i obaveštenje
        $this->browser->loginUser($this->worker);
        $this->browser->request('GET', '/podsetnik');
        self::assertStringContainsString('TAJNA STAVKA', $this->body());
        $this->browser->request('GET', '/notifications');
        self::assertStringContainsString('TAJNO OBAVESTENJE', $this->body());
    }

    public function testTodoVoterDeniesEverythingForSwitchUserToken(): void
    {
        $voter = new TodoVoter();
        $original = new UsernamePasswordToken($this->admin, 'main', $this->admin->getRoles());
        $switched = new SwitchUserToken($this->worker, 'main', $this->worker->getRoles(), $original);
        foreach ([TodoVoter::VIEW, TodoVoter::EDIT, TodoVoter::DELETE] as $attribute) {
            self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($switched, $this->item, [$attribute]), $attribute.' za vlasnika preko impersonacije');
            // bez impersonacije vlasnik i dalje sme
            self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new UsernamePasswordToken($this->worker, 'main', $this->worker->getRoles()), $this->item, [$attribute]));
        }
    }
}
