<?php

namespace App\Tests\Twig;

use App\Twig\DateExtension;
use PHPUnit\Framework\TestCase;

class DateExtensionTest extends TestCase
{
    public function testDayNames(): void
    {
        $ext = new DateExtension();
        self::assertSame('četvrtak', $ext->dayName(new \DateTimeImmutable('2026-11-12')));
        self::assertSame('nedelja', $ext->dayName(new \DateTimeImmutable('2026-11-15')));
        self::assertSame('ponedeljak', $ext->dayName('2026-11-16'));
        self::assertSame('čet', $ext->dayName('2026-11-12', 'short'));
    }
}
