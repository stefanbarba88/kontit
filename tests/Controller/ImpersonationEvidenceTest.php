<?php

namespace App\Tests\Controller;

use App\Entity\ImpersonationLog;
use App\Entity\User;
use App\Enum\ImpersonationEnd;
use App\Enum\Role;
use App\Tests\Support\DatabaseTestCase;

/** Evidencija pristupa (SPEC 8s): lista za superadmina (Sigurnost) i "Pristupi mom nalogu" u profilu korisnika. */
class ImpersonationEvidenceTest extends DatabaseTestCase
{
    private User $super;
    private User $admin;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = $this->user('Super', Role::SUPERADMIN);
        $this->admin = $this->user('Admin', Role::ADMIN);
        $this->worker = $this->user('Radnik');
        $now = new \DateTimeImmutable();
        $log = new ImpersonationLog($this->admin, $this->worker, '10.1.2.3', $now->modify('-30 minutes'));
        $log->enterEditMode($now->modify('-25 minutes'));
        $log->end(ImpersonationEnd::MANUAL, $now->modify('-10 minutes'));
        $this->em->persist($log);
        $open = new ImpersonationLog($this->super, $this->admin, '10.9.9.9', $now->modify('-5 minutes'));
        $this->em->persist($open);
        $this->em->flush();
    }

    private function body(): string
    {
        return (string) $this->browser->getResponse()->getContent();
    }

    public function testSuperadminSeesTheFullList(): void
    {
        $this->browser->loginUser($this->super);
        $this->browser->request('GET', '/admin/security');
        self::assertResponseIsSuccessful();
        $html = $this->body();
        self::assertStringContainsString($this->admin->getFullName(), $html);
        self::assertStringContainsString($this->worker->getFullName(), $html);
        self::assertStringContainsString('10.1.2.3', $html, 'IP adresa');
        self::assertStringContainsString('Izmene', $html, 'režim izmena');
        self::assertStringContainsString('U toku', $html, 'otvoren pristup');
        self::assertStringContainsString('href="/admin/security"', $html, 'stavka menija');
    }

    public function testNobodyElseSeesTheSecurityPageOrMenuItem(): void
    {
        $leader = $this->user('Sef', Role::SEF);
        $this->em->flush();
        foreach ([$this->admin, $this->worker, $leader] as $user) {
            $this->browser->loginUser($user);
            $this->browser->request('GET', '/admin/security');
            self::assertResponseStatusCodeSame(403, $user->getFullName());
            $this->browser->request('GET', '/');
            self::assertStringNotContainsString('href="/admin/security"', $this->body(), 'nema stavke menija: '.$user->getFullName());
        }
    }

    public function testSecurityPageIsClosedWhileImpersonating(): void
    {
        $this->browser->loginUser($this->super);
        $crawler = $this->browser->request('GET', '/admin/users/'.$this->admin->getId());
        $token = (string) $crawler->filter('input[name=_switch_user]')->closest('form')->filter('input[name=_token]')->attr('value');
        $this->browser->request('POST', '/impersonation/'.$this->admin->getId(), ['_switch_user' => $this->admin->getEmail(), '_token' => $token]);
        $this->browser->request('GET', '/admin/security');
        self::assertResponseStatusCodeSame(403, 'čak ni superadmin kroz impersonaciju ne vidi evidenciju');
    }

    public function testUserSeesWhoAccessedTheirAccountReadOnly(): void
    {
        $this->browser->loginUser($this->worker);
        $this->browser->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        $html = $this->body();
        self::assertStringContainsString('Pristupi mom nalogu', $html);
        self::assertStringContainsString($this->admin->getFullName(), $html);
        self::assertStringContainsString('Administrator', $html, 'rola pristupača');
        self::assertStringContainsString('Izmene', $html, 'režim');
        self::assertStringNotContainsString('10.1.2.3', $html, 'IP adresu vidi samo superadmin');
        self::assertStringNotContainsString($this->super->getFullName(), $html, 'tuđi pristupi nisu vidljivi');
    }

    public function testUserWithoutAccessesSeesEmptyState(): void
    {
        $other = $this->user('Drugi');
        $this->em->flush();
        $this->browser->loginUser($other);
        $this->browser->request('GET', '/profile');
        self::assertStringContainsString('Nema evidentiranih pristupa', $this->body());
    }
}
