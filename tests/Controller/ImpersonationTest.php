<?php

namespace App\Tests\Controller;

use App\Entity\ImpersonationLog;
use App\Entity\User;
use App\Enum\ImpersonationEnd;
use App\Enum\ImpersonationMode;
use App\Enum\Role;
use App\Tests\Support\DatabaseTestCase;

/**
 * "Prikaži kao korisnik" (SPEC 8s): ko sme, POST + CSRF, baner, samo pregled, izlaz, istek, evidencija.
 */
class ImpersonationTest extends DatabaseTestCase
{
    private const BANNER = 'Gledaš kao:';

    /** Ulogovan je $actor; vraća važeći CSRF token za pokretanje (iz forme na stranici nižeg korisnika). */
    private function loginAndToken(User $actor, User $someLower): string
    {
        $this->em->flush();
        $this->browser->loginUser($actor);
        $crawler = $this->browser->request('GET', '/admin/users/'.$someLower->getId());
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name=_switch_user]')->closest('form')?->filter('input[name=_token]')->attr('value');
        self::assertNotEmpty($token, 'forma "Prikaži kao ovaj korisnik" mora da postoji za nižeg korisnika');

        return (string) $token;
    }

    private function switchTo(User $target, string $token): void
    {
        $this->browser->request('POST', '/impersonation/'.$target->getId(), ['_switch_user' => $target->getEmail(), '_token' => $token]);
    }

    private function exit(User $target, string $token): void
    {
        $this->browser->request('POST', '/impersonation/'.$target->getId(), ['_switch_user' => '_exit', '_token' => $token]);
    }

    private function body(): string
    {
        return (string) $this->browser->getResponse()->getContent();
    }

    private function lastLog(): ?ImpersonationLog
    {
        $this->em->clear();

        return $this->em->getRepository(ImpersonationLog::class)->findOneBy([], ['id' => 'DESC']);
    }

    /** @return array{0: User, 1: User, 2: User, 3: User} superadmin, admin, šef, radnik */
    private function users(): array
    {
        return [$this->user('Super', Role::SUPERADMIN), $this->user('Admin', Role::ADMIN), $this->user('Sef', Role::SEF), $this->user('Radnik')];
    }

    public function testSuperadminCanStartOnAdminLeaderAndWorker(): void
    {
        [$super, $admin, $sef, $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        foreach ([$admin, $sef, $radnik] as $target) {
            $this->switchTo($target, $token);
            self::assertResponseRedirects('/', 302, 'pokretanje: '.$target->getEmail());
            $this->browser->followRedirect();
            self::assertStringContainsString(self::BANNER, $this->body());
            self::assertStringContainsString($target->getFullName(), $this->body());
            $this->exit($target, $token);
            self::assertResponseRedirects();
            $this->browser->request('GET', '/');
            self::assertStringNotContainsString(self::BANNER, $this->body(), 'posle izlaza nema banera');
        }
    }

    public function testAdminCanStartOnLeaderAndWorkerButNotOnAdminOrSuperadmin(): void
    {
        [$super, $admin, $sef, $radnik] = $this->users();
        $otherAdmin = $this->user('Admin2', Role::ADMIN);
        $token = $this->loginAndToken($admin, $radnik);
        foreach ([$sef, $radnik] as $target) {
            $this->switchTo($target, $token);
            self::assertResponseRedirects('/');
            $this->exit($target, $token);
        }
        foreach ([$otherAdmin, $super] as $target) {
            $this->switchTo($target, $token);
            self::assertResponseStatusCodeSame(403, 'admin ne sme na '.$target->getEmail());
            $this->browser->request('GET', '/');
            self::assertStringNotContainsString(self::BANNER, $this->body());
        }
        // dugme se ne prikazuje uz više uloge
        $crawler = $this->browser->request('GET', '/admin/users/'.$otherAdmin->getId());
        self::assertCount(0, $crawler->filter('input[name=_switch_user]'));
    }

    public function testLeaderAndWorkerNeverImpersonate(): void
    {
        [$super, , $sef, $radnik] = $this->users();
        $other = $this->user('Drugi');
        $this->em->flush();
        foreach ([$sef, $radnik] as $actor) {
            $this->browser->loginUser($actor);
            $this->browser->request('GET', '/admin/users/'.$other->getId());
            self::assertResponseStatusCodeSame(403, 'ni stranica ni dugme');
            // direktan POST sa izmišljenim tokenom
            $this->browser->request('POST', '/impersonation/'.$other->getId(), ['_switch_user' => $other->getEmail(), '_token' => 'x']);
            self::assertResponseStatusCodeSame(403);
            $this->browser->request('GET', '/');
            self::assertStringNotContainsString(self::BANNER, $this->body());
        }
    }

    public function testNeverOnSelfOrInactiveUser(): void
    {
        [$super, $admin, , $radnik] = $this->users();
        $gone = $this->user('Neaktivan')->setIsActive(false);
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($super, $token);
        self::assertResponseStatusCodeSame(403, 'na sebe');
        $this->switchTo($gone, $token);
        self::assertResponseStatusCodeSame(403, 'na deaktiviranog');
    }

    public function testGetSwitchUserIsRejectedAndPostNeedsCsrf(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->browser->request('GET', '/?_switch_user='.urlencode($radnik->getEmail()));
        self::assertResponseStatusCodeSame(403, 'GET ne pokreće impersonaciju');
        $this->browser->request('POST', '/impersonation/'.$radnik->getId(), ['_switch_user' => $radnik->getEmail()]);
        self::assertResponseStatusCodeSame(403, 'bez CSRF tokena');
        $this->browser->request('POST', '/impersonation/'.$radnik->getId(), ['_switch_user' => $radnik->getEmail(), '_token' => 'pogresan']);
        self::assertResponseStatusCodeSame(403, 'sa pogrešnim tokenom');
        $this->browser->request('GET', '/');
        self::assertStringNotContainsString(self::BANNER, $this->body());
        self::assertNull($this->lastLog(), 'ništa nije upisano u evidenciju');
        // posle svega ispravan zahtev radi
        $this->switchTo($radnik, $token);
        self::assertResponseRedirects('/');
    }

    public function testNoNestedImpersonation(): void
    {
        [$super, $admin, $sef, $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($admin, $token);
        self::assertResponseRedirects('/');
        $this->browser->followRedirect();
        $this->switchTo($radnik, $token);
        self::assertResponseStatusCodeSame(403, 'ugnježdavanje nije dozvoljeno');
    }

    public function testViewOnlyBlocksChangesButAllowsReadingAndExit(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($radnik, $token);
        $crawler = $this->browser->followRedirect();
        $csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');

        $this->browser->request('POST', '/ajax/theme', server: ['HTTP_X_CSRF_TOKEN' => $csrf, 'CONTENT_TYPE' => 'application/json'], content: '{"theme":"dark"}');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Samo pregled: izmene nisu dozvoljene.', json_decode($this->body(), true)['message']);
        $this->browser->request('DELETE', '/ajax/checklist/1', server: ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', '/profile', ['x' => 1]);
        self::assertResponseStatusCodeSame(303, 'forma: poruka i povratak, bez izvršavanja');
        $this->em->clear();
        self::assertSame('Radnik', $this->em->find(User::class, $radnik->getId())->getFirstName(), 'ništa nije izmenjeno');

        $this->browser->request('GET', '/tasks');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(self::BANNER, $this->body());

        $this->exit($radnik, $token);
        self::assertResponseRedirects();
        $this->browser->request('GET', '/');
        self::assertStringNotContainsString(self::BANNER, $this->body());
    }

    public function testBannerOnEveryPageWhileImpersonating(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($radnik, $token);
        $this->browser->followRedirect();
        foreach (['/', '/tasks', '/calendar', '/profile', '/pregled-poslova', '/absences', '/notifications', '/clients/mine', '/help'] as $url) {
            $this->browser->request('GET', $url);
            if (404 === $this->browser->getResponse()->getStatusCode()) {
                continue;
            }
            self::assertStringContainsString(self::BANNER, $this->body(), 'baner na '.$url);
            self::assertStringContainsString('samo pregled', $this->body());
            self::assertStringContainsString('name="_switch_user" value="_exit"', $this->body(), 'dugme Izađi');
        }
    }

    public function testModeSwitchAndLog(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($radnik, $token);
        $this->browser->followRedirect();

        $log = $this->lastLog();
        self::assertSame($super->getId(), $log->getActor()->getId());
        self::assertSame($radnik->getId(), $log->getTarget()->getId());
        self::assertSame(ImpersonationMode::VIEW, $log->getMode());
        self::assertTrue($log->isOpen());
        self::assertNotNull($log->getIp());

        $crawler = $this->browser->request('GET', '/');
        $modeToken = (string) $crawler->filter('form[action="/impersonation/mode"] input[name=_token]')->attr('value');
        $this->browser->request('POST', '/impersonation/mode', ['_token' => $modeToken, 'mode' => 'edit']);
        self::assertResponseRedirects();
        $this->browser->request('GET', '/');
        self::assertStringContainsString('radiš u njegovo ime', $this->body());
        $log = $this->lastLog();
        self::assertSame(ImpersonationMode::EDIT, $log->getMode());
        self::assertNotNull($log->getEditStartedAt());

        $this->browser->request('POST', '/impersonation/mode', ['_token' => $modeToken, 'mode' => 'view']);
        $this->browser->request('GET', '/');
        self::assertStringContainsString('samo pregled', $this->body());
        self::assertSame(ImpersonationMode::EDIT, $this->lastLog()->getMode(), 'najviši dostignut režim ostaje u evidenciji');

        $this->exit($radnik, $token);
        $log = $this->lastLog();
        self::assertFalse($log->isOpen());
        self::assertSame(ImpersonationEnd::MANUAL, $log->getEndReason());
    }

    public function testModeRouteNeedsCsrfAndImpersonation(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->browser->request('POST', '/impersonation/mode', ['_token' => 'x', 'mode' => 'edit']);
        self::assertResponseStatusCodeSame(404, 'bez impersonacije ruta ne postoji');
        $this->switchTo($radnik, $token);
        $this->browser->followRedirect();
        $this->browser->request('POST', '/impersonation/mode', ['_token' => 'pogresan', 'mode' => 'edit']);
        $this->browser->request('GET', '/');
        self::assertStringContainsString('samo pregled', $this->body(), 'pogrešan token ne menja režim');
    }

    public function testIdleTimeoutEndsImpersonation(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($radnik, $token);
        $this->browser->followRedirect();
        $session = $this->browser->getRequest()->getSession();
        // poslednja aktivnost pre više od 60 minuta
        $session->set('_imp_last', time() - 61 * 60);
        $session->save();

        $this->browser->request('GET', '/tasks');
        self::assertResponseRedirects('/admin/users/'.$radnik->getId());
        $this->browser->followRedirect();
        self::assertStringNotContainsString(self::BANNER, $this->body());
        self::assertStringContainsString('neaktivnosti', $this->body());
        $log = $this->lastLog();
        self::assertSame(ImpersonationEnd::TIMEOUT, $log->getEndReason());
        self::assertFalse($log->isOpen());
    }

    public function testActivityWithinTheLimitKeepsImpersonation(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($radnik, $token);
        $this->browser->followRedirect();
        $session = $this->browser->getRequest()->getSession();
        $session->set('_imp_last', time() - 59 * 60);
        $session->save();
        $this->browser->request('GET', '/tasks');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(self::BANNER, $this->body());
    }

    public function testLogoutEndsImpersonationAndClosesLog(): void
    {
        [$super, , , $radnik] = $this->users();
        $token = $this->loginAndToken($super, $radnik);
        $this->switchTo($radnik, $token);
        $this->browser->followRedirect();
        $this->browser->request('GET', '/logout');
        $log = $this->lastLog();
        self::assertSame(ImpersonationEnd::LOGOUT, $log->getEndReason());
        $this->browser->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testStaleLogsAreClosedByCommandLogic(): void
    {
        [$super, , , $radnik] = $this->users();
        $this->em->flush();
        $old = new ImpersonationLog($super, $radnik, '127.0.0.1', new \DateTimeImmutable('-3 hours'));
        $fresh = new ImpersonationLog($super, $radnik, '127.0.0.1');
        $this->em->persist($old);
        $this->em->persist($fresh);
        $this->em->flush();

        $closed = self::getContainer()->get(\App\Service\ImpersonationState::class)->closeStale();
        self::assertSame(1, $closed);
        $this->em->clear();
        $old = $this->em->find(ImpersonationLog::class, $old->getId());
        $fresh = $this->em->find(ImpersonationLog::class, $fresh->getId());
        self::assertSame(ImpersonationEnd::STALE, $old->getEndReason());
        self::assertSame($old->getLastActivityAt()->getTimestamp(), $old->getEndedAt()->getTimestamp(), 'kraj je poslednja aktivnost');
        self::assertTrue($fresh->isOpen());
    }

    public function testButtonsAreShownOnlyForAllowedTargets(): void
    {
        [$super, $admin, $sef, $radnik] = $this->users();
        $this->em->flush();
        $this->browser->loginUser($admin);
        $crawler = $this->browser->request('GET', '/admin/users?status=');
        $targets = $crawler->filter('input[name=_switch_user]')->each(fn ($n) => $n->attr('value'));
        self::assertContains($radnik->getEmail(), $targets);
        self::assertContains($sef->getEmail(), $targets);
        self::assertNotContains($admin->getEmail(), $targets, 'ne na sebe');
        self::assertNotContains($super->getEmail(), $targets, 'ne na višu rolu');
    }
}
