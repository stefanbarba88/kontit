<?php

namespace App\Tests\Service;

use App\Service\ReturnUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class ReturnUrlResolverTest extends TestCase
{
    private const FALLBACK = '/tasks/7';

    private function resolver(): ReturnUrlResolver
    {
        return new ReturnUrlResolver(new RequestStack());
    }

    public function testAcceptsRelativePathWithFiltersAndPage(): void
    {
        self::assertSame('/tasks?status=U_RADU&page=2', $this->resolver()->resolve('/tasks?status=U_RADU&page=2', self::FALLBACK));
        self::assertSame('/clients/5?tab=tasks#x', $this->resolver()->resolve('/clients/5?tab=tasks#x', self::FALLBACK));
    }

    /** @return iterable<string, array{mixed}> */
    public static function unsafe(): iterable
    {
        yield 'protocol-relative' => ['//evil.com'];
        yield 'backslash' => ['/\evil.com'];
        yield 'encoded backslash' => ['/%5Cevil.com'];
        yield 'https' => ['https://evil.com'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'encoded slashes' => ['%2F%2Fevil.com'];
        yield 'encoded after slash' => ['/%2F%2Fevil.com'];
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'null' => [null];
        yield 'array' => [['/tasks']];
        yield 'newline' => ["/tasks\r\nLocation: https://evil.com"];
        yield 'no leading slash' => ['tasks'];
    }

    #[DataProvider('unsafe')]
    public function testRejectsUnsafe(mixed $raw): void
    {
        self::assertSame(self::FALLBACK, $this->resolver()->resolve($raw, self::FALLBACK));
        self::assertNull($this->resolver()->sanitize($raw));
    }
}
