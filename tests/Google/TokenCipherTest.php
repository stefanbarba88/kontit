<?php

namespace App\Tests\Google;

use App\Google\TokenCipher;
use PHPUnit\Framework\TestCase;

class TokenCipherTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $cipher = new TokenCipher('tajna');
        $encrypted = $cipher->encrypt('1//refresh-token-ćšž');
        self::assertNotSame('1//refresh-token-ćšž', $encrypted);
        self::assertStringNotContainsString('refresh', $encrypted);
        self::assertSame('1//refresh-token-ćšž', $cipher->decrypt($encrypted));
        self::assertNotSame($encrypted, $cipher->encrypt('1//refresh-token-ćšž'), 'nasumičan nonce/iv');
    }

    public function testWrongKeyOrGarbage(): void
    {
        $encrypted = (new TokenCipher('tajna'))->encrypt('token');
        self::assertNull((new TokenCipher('druga'))->decrypt($encrypted));
        self::assertNull((new TokenCipher('tajna'))->decrypt('o1:nijebase64!!'));
        self::assertNull((new TokenCipher('tajna'))->decrypt(null));
    }
}
