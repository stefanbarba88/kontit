<?php

namespace App\Tests\Nbs;

use App\Nbs\IdentifierChecksum;
use PHPUnit\Framework\TestCase;

final class IdentifierChecksumTest extends TestCase
{
    public function testPib(): void
    {
        self::assertTrue(IdentifierChecksum::isValidPib('100002119'));
        self::assertFalse(IdentifierChecksum::isValidPib('100002118'), 'pogrešna kontrolna cifra');
        self::assertFalse(IdentifierChecksum::isValidPib('10000211'), 'kratak');
        self::assertFalse(IdentifierChecksum::isValidPib('10000211x'));
        self::assertFalse(IdentifierChecksum::isValidPib(null));
    }

    public function testNormalizeMaticniBroj(): void
    {
        self::assertSame('07810318', IdentifierChecksum::normalizeMaticniBroj('7810318'));
    }
}
