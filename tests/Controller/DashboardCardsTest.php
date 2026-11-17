<?php

namespace App\Tests\Controller;

use App\Enum\Role;
use App\Tests\Support\DatabaseTestCase;

/** Početna (SPEC 8p.5): blok Zadaci ima tačno 4 kartice, pored njih kartica Podsetnik (50/50 od lg). */
class DashboardCardsTest extends DatabaseTestCase
{
    /** @dataProvider roles */
    public function testTasksBlockHasOnlyFourCards(Role $role): void
    {
        $user = $this->user('Korisnik', $role);
        $this->em->flush();
        $this->browser->loginUser($user);
        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $this->browser->getResponse()->getContent();

        foreach (['Otvoreni zadaci', 'Kasne', 'Ističu u 3 dana', 'Završeno ovog meseca'] as $label) {
            self::assertStringContainsString($label, $html, $label);
        }
        foreach (['Završeno na vreme', 'Vraćeno na doradu', 'Klijenti (backup)'] as $label) {
            self::assertStringNotContainsString($label, $html, $label.' je uklonjeno sa početne');
        }
        self::assertStringContainsString('kt-dash-top has-todo', $html);
        self::assertStringContainsString('kt-todo-card', $html);
        // tačno 4 KPI kartice u bloku Zadaci
        $block = substr($html, (int) strpos($html, 'kt-dash-stats'), 6000);
        self::assertSame(4, substr_count(substr($block, 0, (int) strpos($block, '</section>')), 'kt-kpi '));
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        yield 'radnik' => [Role::RADNIK];
        yield 'sef' => [Role::SEF];
        yield 'admin' => [Role::ADMIN];
    }
}
