<?php

namespace App\Tests\Nbs;

use App\Nbs\NbsXmlParser;
use PHPUnit\Framework\TestCase;

/**
 * Parser nad sačuvanim odgovorima NBS-a (tests/Fixtures/nbs/). Ako NBS promeni format, ovaj test pada prvi.
 */
final class NbsXmlParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/nbs/'.$name);
    }

    public function testParsesCompany(): void
    {
        $data = (new NbsXmlParser())->parseCompany($this->fixture('company_100002119.xml'), '100002119');

        self::assertNotNull($data);
        self::assertSame('100002119', $data['pib']);
        self::assertSame('07810318', $data['maticniBroj'], 'matični broj se dopunjuje vodećom nulom na 8 cifara');
        self::assertStringContainsString('MILENIJUM OSIGURANJE ADO', $data['name']);
        self::assertSame('Bulevar Milutina Milankovića 3B', $data['address']);
        self::assertSame('11070', $data['postalCode']);
        self::assertSame('1992-02-24', $data['registrationDate']?->format('Y-m-d'));
    }

    public function testParsesAccounts(): void
    {
        $accounts = (new NbsXmlParser())->parseAccounts($this->fixture('accounts_100002119.xml'));

        self::assertNotEmpty($accounts);
        self::assertSame('155-0000000042455-06', $accounts[0]['account']);
        self::assertSame('Halkbank Akcionarsko društvo Beograd', $accounts[0]['bank']);
        self::assertSame(count($accounts), count(array_unique(array_column($accounts, 'account'))));
    }

    public function testTransliteratesCyrillic(): void
    {
        $data = (new NbsXmlParser())->parseCompany($this->fixture('company_cyrillic.xml'), '100002119');

        self::assertSame('ĐORĐE ŠARIĆ PR LJUBIČASTA RADNJA ČAČAK', $data['name']);
        self::assertSame('Njegoševa 12', $data['address']);
        self::assertSame('Čačak', $data['city']);
        self::assertSame('Džem i marmelada', $data['activityName']);
    }

    public function testEmptyResponseMeansNotFound(): void
    {
        self::assertNull((new NbsXmlParser())->parseCompany('', '100002119'));
        self::assertNull((new NbsXmlParser())->parseCompany('<CompanyDataSet />', '100002119'));
    }
}
