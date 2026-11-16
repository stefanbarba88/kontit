<?php

namespace App\Tests\Controller;

use App\Entity\ActivityLog;
use App\Entity\ChecklistItem;
use App\Entity\Task;
use App\Entity\TaskComment;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\EventListener\ImpersonationRules;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Režim izmena "Prikaži kao korisnik" (SPEC 8s): obična radnja prolazi, a u istoriji je stvarni izvršilac sa "u ime".
 * Nikad u tuđe ime: lozinka i mejl, role i pozicije, deaktivacija, impersonacija, potvrde i odobravanja, komentari, dokumenti, Google, ICS, Podsetnik.
 */
class ImpersonationEditModeTest extends DatabaseTestCase
{
    private User $admin;
    private User $worker;
    private Task $task;
    private ChecklistItem $item;
    private string $token = '';
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('Admin', Role::ADMIN);
        $this->worker = $this->user('Radnik');
        $client = $this->client('Klijent', $this->worker);
        $this->task = $this->task('Zadatak za proveru', $this->admin, [$this->worker], $client, null, TaskStatus::U_RADU);
        $this->item = new ChecklistItem($this->task, 'Stavka', 1, $this->worker);
        $this->em->persist($this->item);
        $this->em->flush();
    }

    private function impersonate(bool $edit): void
    {
        $this->browser->loginUser($this->admin);
        $crawler = $this->browser->request('GET', '/admin/users/'.$this->worker->getId());
        $this->token = (string) $crawler->filter('input[name=_switch_user]')->closest('form')->filter('input[name=_token]')->attr('value');
        $this->browser->request('POST', '/impersonation/'.$this->worker->getId(), ['_switch_user' => $this->worker->getEmail(), '_token' => $this->token]);
        $crawler = $this->browser->followRedirect();
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
        if ($edit) {
            $modeToken = (string) $crawler->filter('form[action="/impersonation/mode"] input[name=_token]')->attr('value');
            $this->browser->request('POST', '/impersonation/mode', ['_token' => $modeToken, 'mode' => 'edit']);
            self::assertResponseRedirects();
        }
    }

    private function body(): string
    {
        return (string) $this->browser->getResponse()->getContent();
    }

    private function json(string $method, string $url, array $payload = []): array
    {
        $this->browser->request($method, $url, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->csrf], json_encode($payload));

        return json_decode($this->body(), true) ?? [];
    }

    private function lastLog(): ?ActivityLog
    {
        $this->em->clear();

        return $this->em->getRepository(ActivityLog::class)->findOneBy(['action' => ActivityAction::CHECKED], ['id' => 'DESC']);
    }

    public function testOrdinaryActionPassesAndHistoryNamesTheRealExecutor(): void
    {
        $this->impersonate(edit: true);
        $res = $this->json('POST', '/ajax/checklist/'.$this->item->getId().'/toggle', ['done' => true]);
        self::assertTrue($res['success'] ?? false, 'obična radnja u ime korisnika prolazi');
        $this->em->clear();
        self::assertTrue($this->em->find(ChecklistItem::class, $this->item->getId())->isDone());

        $log = $this->lastLog();
        self::assertNotNull($log);
        self::assertSame($this->admin->getId(), $log->getUser()?->getId(), 'u istoriji je STVARNI izvršilac');
        self::assertSame($this->worker->getId(), $log->getOnBehalfOf()?->getId(), 'korisnik u čije se ime radi');

        // istorija zadatka prikazuje oboje
        $this->browser->request('GET', '/ajax/tasks/'.$this->task->getId().'/activity');
        $html = json_decode($this->body(), true)['data']['html'] ?? '';
        self::assertStringContainsString($this->admin->getFullName(), $html);
        self::assertStringContainsString('u ime: '.$this->worker->getFullName(), $html);
    }

    public function testSameActionByTheWorkerHimselfHasNoOnBehalfOf(): void
    {
        $this->browser->loginUser($this->worker);
        $crawler = $this->browser->request('GET', '/tasks/'.$this->task->getId());
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
        $res = $this->json('POST', '/ajax/checklist/'.$this->item->getId().'/toggle', ['done' => true]);
        self::assertTrue($res['success'] ?? false);
        $log = $this->lastLog();
        self::assertSame($this->worker->getId(), $log->getUser()?->getId());
        self::assertNull($log->getOnBehalfOf(), 'bez impersonacije nema "u ime"');
    }

    public function testViewModeStillBlocksEvenTheOrdinaryAction(): void
    {
        $this->impersonate(edit: false);
        $res = $this->json('POST', '/ajax/checklist/'.$this->item->getId().'/toggle', ['done' => true]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(ImpersonationRules::VIEW_ONLY, $res['message']);
        $this->em->clear();
        self::assertFalse($this->em->find(ChecklistItem::class, $this->item->getId())->isDone());
    }

    /** @return iterable<string, array{string, string, array<string, mixed>}> forbidden u režimu izmena: [metod, adresa, telo] */
    public static function forbiddenInEditMode(): iterable
    {
        yield 'komentar' => ['POST', '/ajax/tasks/%task%/comments', ['body' => 'Ja sam radnik']];
        yield 'izmena komentara' => ['POST', '/ajax/comments/1/edit', ['body' => 'x']];
        yield 'potvrda zadatka' => ['POST', '/tasks/%task%/approve', ['_token' => 'x']];
        yield 'vraćanje na doradu' => ['POST', '/tasks/%task%/return', ['_token' => 'x', 'reason' => 'x']];
        yield 'otkazivanje zadatka' => ['POST', '/tasks/%task%/cancel', ['_token' => 'x', 'reason' => 'x']];
        yield 'odobravanje odsustva' => ['POST', '/absences/1/approve', ['_token' => 'x']];
        yield 'odbijanje odsustva' => ['POST', '/absences/1/reject', ['_token' => 'x']];
        yield 'poništavanje automatskog odobrenja' => ['POST', '/absences/1/revoke', ['_token' => 'x']];
        yield 'odobravanje posla' => ['POST', '/client-jobs/1/approve', ['_token' => 'x']];
        yield 'profil: lozinka i mejl' => ['POST', '/profile', ['x' => '1']];
        yield 'korisnik: izmena (rola, pozicija)' => ['POST', '/admin/users/%worker%/edit', ['x' => '1']];
        yield 'korisnik: deaktivacija' => ['POST', '/admin/users/%worker%/toggle', ['_token' => 'x']];
        yield 'korisnik: nov' => ['POST', '/admin/users/new', ['x' => '1']];
        yield 'dokumenti: otpremanje' => ['POST', '/users/%worker%/documents', ['x' => '1']];
        yield 'dokumenti: brisanje' => ['POST', '/documents/1/delete', ['_token' => 'x']];
        yield 'Google: raskid' => ['POST', '/profile/google/disconnect', ['_token' => 'x']];
        yield 'ICS link' => ['POST', '/profile/ics', ['_token' => 'x']];
        yield 'nova impersonacija' => ['POST', '/impersonation/%worker%', ['_switch_user' => 'x@y.z']];
    }

    #[DataProvider('forbiddenInEditMode')]
    public function testForbiddenEvenInEditMode(string $method, string $url, array $payload): void
    {
        $this->impersonate(edit: true);
        $url = str_replace(['%task%', '%worker%'], [(string) $this->task->getId(), (string) $this->worker->getId()], $url);
        if (str_starts_with($url, '/ajax/')) {
            $res = $this->json($method, $url, $payload);
            self::assertResponseStatusCodeSame(403, "$method $url");
            self::assertSame(ImpersonationRules::FORBIDDEN, $res['message']);

            return;
        }
        $this->browser->request($method, $url, $payload);
        if (str_starts_with($url, '/impersonation/')) {
            self::assertResponseStatusCodeSame(403, 'nema ugnježdavanja ni u režimu izmena');

            return;
        }
        self::assertResponseStatusCodeSame(303, "$method $url");
        $this->browser->request('GET', '/');
        self::assertStringContainsString(ImpersonationRules::FORBIDDEN, $this->body(), 'poruka korisniku');
    }

    public function testGoogleConnectIsForbiddenInBothModes(): void
    {
        foreach ([false, true] as $edit) {
            $this->impersonate($edit);
            $this->browser->request('GET', '/connect/google');
            self::assertResponseStatusCodeSame(303, 'povezivanje Google naloga tuđeg korisnika '.($edit ? '(izmene)' : '(pregled)'));
            $this->browser->request('GET', '/connect/google/check');
            self::assertResponseStatusCodeSame(303);
            $this->browser->request('POST', '/impersonation/'.$this->worker->getId(), ['_switch_user' => '_exit', '_token' => $this->token]);
        }
    }

    public function testNothingChangedAfterForbiddenRequests(): void
    {
        $this->impersonate(edit: true);
        $this->json('POST', '/ajax/tasks/'.$this->task->getId().'/comments', ['body' => 'Ne sme']);
        $this->browser->request('POST', '/tasks/'.$this->task->getId().'/approve', ['_token' => 'x']);
        $this->browser->request('POST', '/admin/users/'.$this->worker->getId().'/toggle', ['_token' => 'x']);
        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(TaskComment::class)->findAll(), 'komentar nije napisan u ime radnika');
        self::assertTrue($this->em->find(User::class, $this->worker->getId())->isActive());
        self::assertNotSame(TaskStatus::ZAVRSENO, $this->em->find(Task::class, $this->task->getId())->getStatus());
    }

    public function testSameRequestsWorkNormallyOutsideImpersonation(): void
    {
        // komentar: radnik ga piše sam
        $this->browser->loginUser($this->worker);
        $crawler = $this->browser->request('GET', '/tasks/'.$this->task->getId());
        self::assertStringContainsString('id="comment-add"', $this->body(), 'forma za komentar postoji');
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
        $res = $this->json('POST', '/ajax/tasks/'.$this->task->getId().'/comments', ['body' => 'Moj komentar']);
        self::assertTrue($res['success'] ?? false);
        $this->em->clear();
        $comments = $this->em->getRepository(TaskComment::class)->findAll();
        self::assertCount(1, $comments);
        self::assertSame($this->worker->getId(), $comments[0]->getAuthor()->getId());

        // potvrda: admin potvrđuje zadatak na proveri
        $this->em->getConnection()->executeStatement('UPDATE task SET status = ? WHERE id = ?', [TaskStatus::NA_PROVERI->value, $this->task->getId()]);
        $this->admin = $this->em->find(User::class, $this->admin->getId());
        $this->browser->loginUser($this->admin);
        $crawler = $this->browser->request('GET', '/tasks/'.$this->task->getId());
        $form = $crawler->filter('form[action="/tasks/'.$this->task->getId().'/approve"]');
        self::assertCount(1, $form, 'admin vidi dugme Potvrdi');
        $this->browser->request('POST', '/tasks/'.$this->task->getId().'/approve', ['_token' => $form->filter('input[name=_token]')->attr('value')]);
        $this->em->clear();
        self::assertSame(TaskStatus::ZAVRSENO, $this->em->find(Task::class, $this->task->getId())->getStatus());
    }

    public function testCommentFormAndDecisionButtonsAreHiddenWhileImpersonating(): void
    {
        $task = $this->em->find(Task::class, $this->task->getId());
        $task->setStatus(TaskStatus::NA_PROVERI);
        $this->em->flush();
        $this->impersonate(edit: true);
        $this->browser->request('GET', '/tasks/'.$this->task->getId());
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('id="comment-add"', $this->body());
        self::assertStringContainsString('Komentarisanje nije dostupno', $this->body());
        self::assertStringNotContainsString('/approve"', $this->body(), 'nema dugmeta Potvrdi');
    }

    public function testVotersAreTheSecondLineOfDefence(): void
    {
        // meta je ADMIN, pa bi bez impersonacije sve bilo dozvoljeno; sa SwitchUserToken odluke, komentari i izmene korisnika ne smeju
        $target = $this->user('Meta', Role::ADMIN);
        $this->task->setStatus(TaskStatus::NA_PROVERI);
        $absence = null;
        $this->em->flush();
        $normal = new UsernamePasswordToken($target, 'main', $target->getRoles());
        $switched = new SwitchUserToken($target, 'main', $target->getRoles(), new UsernamePasswordToken($this->admin, 'main', $this->admin->getRoles()));
        $adm = self::getContainer()->get('security.access.decision_manager');

        foreach (['TASK_APPROVE', 'TASK_CANCEL', 'TASK_COMMENT'] as $attribute) {
            self::assertTrue($adm->decide($normal, [$attribute], $this->task), $attribute.' bez impersonacije');
            self::assertFalse($adm->decide($switched, [$attribute], $this->task), $attribute.' kroz impersonaciju');
        }
        self::assertTrue($adm->decide($switched, ['TASK_VIEW'], $this->task), 'gledanje je dozvoljeno');
        self::assertTrue($adm->decide($normal, ['USER_EDIT'], $this->worker));
        self::assertFalse($adm->decide($switched, ['USER_EDIT'], $this->worker));
        self::assertFalse($adm->decide($switched, ['USER_MANAGE']));
        self::assertTrue($adm->decide($normal, ['USER_DOCUMENT_UPLOAD'], $this->worker));
        self::assertFalse($adm->decide($switched, ['USER_DOCUMENT_UPLOAD'], $this->worker));
    }

    public function testRulesAreExplicit(): void
    {
        $rules = new ImpersonationRules();
        $req = static fn (string $action = ''): Request => new Request(attributes: ['action' => $action]);
        foreach (array_keys(ImpersonationRules::FORBIDDEN_ROUTES) as $route) {
            self::assertSame(ImpersonationRules::FORBIDDEN, $rules->denial($route, $req(), true), $route);
        }
        foreach (['approve', 'reject', 'revoke'] as $action) {
            self::assertSame(ImpersonationRules::FORBIDDEN, $rules->denial('absence_action', $req($action), true), $action);
        }
        self::assertNull($rules->denial('absence_action', $req('cancel'), true), 'otkazivanje sopstvenog zahteva nije odluka');
        self::assertNull($rules->denial('ajax_checklist_toggle', $req(), true));
        self::assertNull($rules->denial('ajax_theme', $req(), true));
        self::assertSame(ImpersonationRules::VIEW_ONLY, $rules->denial('ajax_checklist_toggle', $req(), false));
        self::assertTrue($rules->isPrivate('todo_index'));
        self::assertTrue($rules->isPrivate('ajax_todo_due'));
        self::assertFalse($rules->isPrivate('ajax_theme'));
    }
}
