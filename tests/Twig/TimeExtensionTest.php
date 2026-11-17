<?php

namespace App\Tests\Twig;

use App\Service\TimeFormat;
use App\Tests\Support\DatabaseTestCase;
use Twig\Environment;

class TimeExtensionTest extends DatabaseTestCase
{
    private function render(string $template, array $vars = []): string
    {
        return trim(self::getContainer()->get(Environment::class)->createTemplate($template)->render($vars));
    }

    public function testFiltersAre24hInBelgradeZone(): void
    {
        $utc = new \DateTimeImmutable('2026-11-17 13:30:00', new \DateTimeZone('UTC')); // Beograd: 14:30 (zimsko vreme)
        self::assertSame('14:30', $this->render('{{ d|kt_time }}', ['d' => $utc]));
        self::assertSame('17.11.2026. 14:30', $this->render('{{ d|kt_datetime }}', ['d' => $utc]));
        self::assertSame('17.11. 14:30', $this->render('{{ d|kt_datetime_short }}', ['d' => $utc]));
        $pm = new \DateTimeImmutable('2026-11-17 21:05:00', new \DateTimeZone('UTC')); // 22:05, nikad "10:05 PM"
        self::assertSame('22:05', $this->render('{{ d|kt_time }}', ['d' => $pm]));
        self::assertDoesNotMatchRegularExpression('/AM|PM/i', $this->render('{{ d|kt_datetime }}', ['d' => $pm]));
    }

    public function testFiltersGiveSameOutputAsTheOldDateFilter(): void
    {
        $d = new \DateTimeImmutable('2026-07-03 08:07:00', new \DateTimeZone('UTC'));
        self::assertSame($this->render("{{ d|date('d.m.Y. H:i') }}", ['d' => $d]), $this->render('{{ d|kt_datetime }}', ['d' => $d]));
        self::assertSame($this->render("{{ d|date('d.m. H:i') }}", ['d' => $d]), $this->render('{{ d|kt_datetime_short }}', ['d' => $d]));
        self::assertSame($this->render("{{ d|date('H:i') }}", ['d' => $d]), $this->render('{{ d|kt_time }}', ['d' => $d]));
    }

    public function testOptionsAre48StepsAndKeepExistingValue(): void
    {
        $options = TimeFormat::options(30);
        self::assertCount(48, $options);
        self::assertSame(['00:00', '00:30', '23:30'], [$options[0], $options[1], $options[47]]);
        $with = TimeFormat::options(30, '14:15');
        self::assertCount(49, $with);
        self::assertSame(['14:00', '14:15', '14:30'], \array_slice($with, 28, 3));
        self::assertCount(48, TimeFormat::options(30, 'nije vreme'));
        self::assertSame(30, self::getContainer()->getParameter('app.time.step_minutes'));
    }

    public function testMacroRendersAllOptionsAndOffStepValue(): void
    {
        $html = $this->render("{% import 'components/time_select.html.twig' as t %}{{ t.select('x', 'time', '14:15', 'Bez vremena') }}");
        self::assertSame(50, substr_count($html, '<option'), 'prazna opcija + 48 koraka + dodatna 14:15');
        self::assertStringContainsString('<option value="">Bez vremena</option>', $html);
        self::assertStringContainsString('<option value="14:15" selected>14:15</option>', $html);
        self::assertStringContainsString('class="form-select"', $html);
        self::assertStringNotContainsString('type="time"', $html);

        $plain = $this->render("{% import 'components/time_select.html.twig' as t %}{{ t.select('x', 'time', '09:00') }}");
        self::assertSame(48, substr_count($plain, '<option'));
        self::assertStringContainsString('<option value="09:00" selected>09:00</option>', $plain);
    }

    public function testBasePageHasStepMeta(): void
    {
        $user = $this->user('Ja');
        $this->em->flush();
        $this->browser->loginUser($user);
        $this->browser->request('GET', '/');
        self::assertStringContainsString('<meta name="kt-time-step" content="30">', (string) $this->browser->getResponse()->getContent());
    }

    public function testRemindModalUsesTimeSelectWithDefaultNineOClock(): void
    {
        $user = $this->user('Ja');
        $this->em->flush();
        $this->browser->loginUser($user);
        $crawler = $this->browser->request('GET', '/');
        $select = $crawler->filter('select#kt-remind-time');
        self::assertCount(1, $select);
        self::assertCount(48, $select->filter('option'));
        self::assertSame('09:00', $select->filter('option[selected]')->attr('value'));
        self::assertStringNotContainsString('type="time"', (string) $this->browser->getResponse()->getContent());
    }
}
