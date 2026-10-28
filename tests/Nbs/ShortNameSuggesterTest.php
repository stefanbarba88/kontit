<?php

namespace App\Tests\Nbs;

use App\Nbs\ShortNameSuggester;
use PHPUnit\Framework\TestCase;

class ShortNameSuggesterTest extends TestCase
{
    /**
     * @dataProvider names
     */
    public function testSuggest(string $name, ?string $nbsShort, string $expected): void
    {
        self::assertSame($expected, ShortNameSuggester::suggest($name, $nbsShort));
    }

    /**
     * @return iterable<array{string, ?string, string}>
     */
    public static function names(): iterable
    {
        yield 'doo i delatnost' => ['Mlekara Ivanjica doo za proizvodnju i promet', null, 'Mlekara Ivanjica'];
        yield 'd.o.o. velikim slovima' => ['MLEKARA IVANJICA D.O.O. IVANJICA', null, 'Mlekara Ivanjica'];
        yield 'NBS kratak naziv ima prednost' => ['DRUŠTVO ZA PROIZVODNJU MLEKARA DOO', 'MLEKARA DOO IVANJICA', 'Mlekara'];
        yield 'preduzetnik PR' => ['Marko Marković PR Knjigovodstvena agencija Konto Beograd', null, 'Marko Marković'];
        yield 'forma na početku' => ['DOO Sloga', null, 'Sloga'];
        yield 'AD' => ['Energoprojekt AD Beograd', null, 'Energoprojekt'];
        yield 'bez forme' => ['Udruženje knjigovođa', null, 'Udruženje knjigovođa'];
        yield 'reč koja počinje sa pr se ne seče' => ['Promet Plus doo', null, 'Promet Plus'];
        yield 'radnja preduzetnika' => ['SAMOSTALNA UGOSTITELJSKA RADNJA ORION RADA GOLUBOVIĆ PR BAJINA BAŠTA', null, 'Orion Rada Golubović'];
        yield 'vlasnik i ortačko društvo' => ['AUTO ŠKOLA AS OD BAJINA BAŠTA VL. SREĆKOVIĆ JOVAN', null, 'Auto Škola As'];
        yield 'društvo sa ograničenom odgovornošću' => ['ERKOM DRUŠTVO SA OGRANIČENOM ODGOVORNOŠĆU IVANJICA', null, 'Erkom'];
        yield 'najviše 40 karaktera' => [str_repeat('Dugačko ime ', 6).'doo', null, 'Dugačko ime Dugačko ime Dugačko ime Duga'];
    }
}
