<?php

namespace App\Tests\Http;

use App\Http\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

class QueryTest extends TestCase
{
    public function testIntAndId(): void
    {
        $bag = new InputBag(['a' => '5', 'empty' => '', 'text' => 'type', 'neg' => '-2', 'arr' => ['1'], 'zero' => '0']);
        $this->assertSame(5, Query::int($bag, 'a'));
        $this->assertNull(Query::int($bag, 'empty'));
        $this->assertSame(7, Query::int($bag, 'text', 7));
        $this->assertNull(Query::int($bag, 'arr'));
        $this->assertNull(Query::int($bag, 'missing'));
        $this->assertSame(5, Query::id($bag, 'a'));
        $this->assertNull(Query::id($bag, 'neg'));
        $this->assertNull(Query::id($bag, 'zero'));
    }

    public function testBool(): void
    {
        $bag = new InputBag(['y' => '1', 'n' => '0', 'junk' => 'x', 'empty' => '']);
        $this->assertTrue(Query::bool($bag, 'y'));
        $this->assertFalse(Query::bool($bag, 'n'));
        $this->assertFalse(Query::bool($bag, 'junk'));
        $this->assertFalse(Query::bool($bag, 'empty'));
        $this->assertFalse(Query::bool($bag, 'missing'));
    }
}
