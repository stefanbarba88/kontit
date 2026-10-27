<?php

namespace App\Nbs;

/**
 * Parsira XML odgovore NBS servisa (GetCompany i GetCompanyAccount) u niz spreman za Client::applyNbs().
 * Odvojeno od SOAP poziva da bi imalo test nad sačuvanim primerom (tests/Fixtures/nbs/).
 */
final class NbsXmlParser
{
    /**
     * @return array<string, mixed>|null null kad firma nije nađena
     */
    public function parseCompany(string $xml, string $pib): ?array
    {
        $rows = $this->rows($xml, 'Company');
        if ([] === $rows) {
            return null;
        }

        $company = $rows[0];
        foreach ($rows as $row) {
            if ($this->text($row['TaxIdentificationNumber'] ?? '') === $pib) {
                $company = $row;
                break;
            }
        }
        $name = $this->text($company['Name'] ?? '');
        if ('' === $name) {
            return null;
        }

        $type = $this->text($company['CompanyTypeID'] ?? '');
        $status = $this->text($company['CompanyStatusID'] ?? '');

        return [
            'name' => $name,
            'shortName' => $this->text($company['ShortName'] ?? ''),
            'pib' => $this->text($company['TaxIdentificationNumber'] ?? '') ?: $pib,
            'maticniBroj' => IdentifierChecksum::normalizeMaticniBroj($this->text($company['NationalIdentificationNumber'] ?? '')),
            'address' => $this->text($company['Address'] ?? ''),
            'postalCode' => $this->text($company['PostalCode'] ?? ''),
            'city' => $this->text($company['City'] ?? ''),
            'municipality' => $this->text($company['Municipality'] ?? ''),
            'region' => $this->text($company['Region'] ?? ''),
            'phone' => $this->text($company['Phone'] ?? '') ?: $this->text($company['Phone2'] ?? ''),
            'email' => $this->text($company['Email'] ?? ''),
            'activityName' => $this->text($company['ActivityName'] ?? ''),
            'registrationDate' => $this->date($company['RegistrationDate'] ?? ''),
            'foundingDate' => $this->date($company['FoundingDate'] ?? ''),
            'companyTypeId' => '' !== $type ? (int) $type : null,
            'statusId' => '' !== $status ? (int) $status : null,
            'accounts' => [],
        ];
    }

    /**
     * Tekući računi (samo aktivni, bez duplikata), npr. [['account' => '155-0000000042455-06', 'bank' => 'Halkbank ...']].
     *
     * @return list<array{account: string, bank: string}>
     */
    public function parseAccounts(string $xml): array
    {
        $accounts = [];
        foreach ($this->rows($xml, 'CompanyAccount') as $row) {
            $account = $this->text($row['Account'] ?? '');
            if ('' === $account || '0' !== ($this->text($row['CompanyAccountStatusID'] ?? '0') ?: '0')) {
                continue;
            }
            $accounts[$account] = ['account' => $account, 'bank' => $this->text($row['BankName'] ?? '')];
        }

        return array_values($accounts);
    }

    /**
     * <DataSet><Item>…</Item><Item>…</Item></DataSet> u listu nizova.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $xml, string $element): array
    {
        $xml = trim($xml);
        if ('' === $xml) {
            return [];
        }
        $parsed = @simplexml_load_string($xml);
        if (false === $parsed) {
            throw new \UnexpectedValueException('NBS je vratio neispravan XML.');
        }

        $rows = [];
        foreach ($parsed->{$element} as $item) {
            $rows[] = json_decode((string) json_encode($item), true) ?: [];
        }

        return $rows;
    }

    /**
     * Prazan XML element postaje prazan niz; ćirilica se preslovljava u latinicu.
     */
    private function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value ? reset($value) : '';
        }

        return Transliterator::toLatin(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        $text = $this->text($value);
        if ('' === $text) {
            return null;
        }
        try {
            return (new \DateTimeImmutable(substr($text, 0, 10)))->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }
}
