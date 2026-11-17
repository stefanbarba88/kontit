<?php

namespace App\Tests\Controller;

use App\Entity\AbsenceType;
use App\Enum\Role;
use App\Tests\Support\DatabaseTestCase;

/** Regresija: prazan ili neispravan parametar (npr. `type=`) ne sme da ruši AJAX preview ni liste (HTTP 500). */
class AbsencePreviewTest extends DatabaseTestCase
{
    private function typeId(): int
    {
        $type = (new AbsenceType())->setName('Godišnji test');
        $this->em->persist($type);
        $this->em->flush();

        return $type->getId();
    }

    /** @return array<string, mixed> */
    private function preview(string $query): array
    {
        $this->browser->request('GET', '/ajax/absences/preview?'.$query);

        return ['status' => $this->browser->getResponse()->getStatusCode(), 'json' => json_decode((string) $this->browser->getResponse()->getContent(), true)];
    }

    /** @dataProvider roles */
    public function testPreviewWithValidType(Role $role): void
    {
        $user = $this->user('Korisnik', $role);
        $typeId = $this->typeId();
        $this->em->flush();
        $this->browser->loginUser($user);

        $r = $this->preview('from=2026-11-20&to=2026-11-20&half=0&user='.$user->getId().'&type='.$typeId);

        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['success']);
    }

    /** @dataProvider roles */
    public function testPreviewWithEmptyOrInvalidParamsDoesNotFail(Role $role): void
    {
        $user = $this->user('Korisnik', $role);
        $this->em->flush();
        $this->browser->loginUser($user);

        foreach (['type=', 'type=abc', 'type=type', 'type=0', 'type[]=1', 'type=&user=&absence=&half='] as $extra) {
            $r = $this->preview('from=2026-11-20&to=2026-11-20&'.$extra);
            $this->assertSame(200, $r['status'], $extra);
            $this->assertTrue($r['json']['success'], $extra);
        }
    }

    /** @dataProvider roles */
    public function testNewFormFromCalendarAndListFiltersWithEmptyValues(Role $role): void
    {
        $user = $this->user('Korisnik', $role);
        $this->em->flush();
        $this->browser->loginUser($user);

        foreach (['/absences/new?from=2026-11-20', '/absences/new', '/absences?team=&year=&page=&history=', '/absences?team=abc&year=xyz&page=-3'] as $url) {
            $this->browser->request('GET', $url);
            $this->assertLessThan(400, $this->browser->getResponse()->getStatusCode(), $url);
        }
    }

    public function testAdminListFilterWithEmptyValues(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $this->em->flush();
        $this->browser->loginUser($admin);
        foreach (['/admin/users?position=', '/admin/users?position=abc'] as $url) {
            $this->browser->request('GET', $url);
            $this->assertSame(200, $this->browser->getResponse()->getStatusCode(), $url);
        }
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        yield 'radnik' => [Role::RADNIK];
        yield 'sef' => [Role::SEF];
        yield 'admin' => [Role::ADMIN];
    }
}
