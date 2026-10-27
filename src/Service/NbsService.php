<?php

namespace App\Service;

use App\Entity\Client;
use App\Enum\LegalForm;

/**
 * NBS CoreXmlService: podaci o firmi po PIB-u (naziv, adresa, matični broj, delatnost, datumi...).
 * Pristupni podaci su u NBS_USERNAME, NBS_PASSWORD i NBS_LICENCE (.env.local).
 */
class NbsService
{
    private const WSDL = 'https://webservices.nbs.rs/CommunicationOfficeService1_0/CoreXmlService.asmx?WSDL';
    private const NAMESPACE = 'http://communicationoffice.nbs.rs';

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $licence,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->username && '' !== $this->password && '' !== $this->licence;
    }

    /**
     * Podaci firme iz NBS-a, spremni za Client::applyNbs().
     *
     * @throws \DomainException sa porukom za korisnika (neispravan PIB, firma nije nađena, servis nedostupan)
     *
     * @return array<string, mixed>
     */
    public function lookup(string $pib): array
    {
        $pib = trim($pib);
        if (!preg_match('/^\d{9}$/', $pib)) {
            throw new \DomainException('PIB mora imati tačno 9 cifara.');
        }
        if (!$this->isConfigured()) {
            throw new \DomainException('NBS pristupni podaci nisu podešeni (NBS_USERNAME, NBS_PASSWORD, NBS_LICENCE u .env.local).');
        }

        $company = $this->fetch($pib);

        $phone = $this->text($company['Phone'] ?? '') ?: $this->text($company['Phone2'] ?? '');
        $name = $this->text($company['Name'] ?? '');
        $type = $this->text($company['CompanyTypeID'] ?? '');
        $status = $this->text($company['CompanyStatusID'] ?? '');

        return [
            'name' => $name,
            'shortName' => $this->text($company['ShortName'] ?? ''),
            'pib' => $this->text($company['TaxIdentificationNumber'] ?? '') ?: $pib,
            'maticniBroj' => $this->text($company['NationalIdentificationNumber'] ?? ''),
            'address' => $this->text($company['Address'] ?? ''),
            'postalCode' => $this->text($company['PostalCode'] ?? ''),
            'city' => $this->text($company['City'] ?? ''),
            'municipality' => $this->text($company['Municipality'] ?? ''),
            'region' => $this->text($company['Region'] ?? ''),
            'phone' => $phone,
            'email' => $this->text($company['Email'] ?? ''),
            'activityName' => $this->text($company['ActivityName'] ?? ''),
            'registrationDate' => $this->date($company['RegistrationDate'] ?? ''),
            'foundingDate' => $this->date($company['FoundingDate'] ?? ''),
            'companyTypeId' => '' !== $type ? (int) $type : null,
            'statusId' => '' !== $status ? (int) $status : null,
            'legalForm' => $this->guessLegalForm($name)->value,
        ];
    }

    /**
     * Osvežava klijenta iz NBS-a (po njegovom PIB-u).
     */
    public function refresh(Client $client): void
    {
        if (null === $client->getPib()) {
            throw new \DomainException('Klijent nema PIB.');
        }
        $client->applyNbs($this->lookup($client->getPib()));
    }

    /**
     * NBS ne vraća pravnu formu kao tekst, pa se pravi pretpostavka po nazivu (može se ispraviti ručno).
     */
    public function guessLegalForm(string $name): LegalForm
    {
        $n = mb_strtoupper($name);

        return match (true) {
            str_contains($n, 'AKCIONARSKO') || (bool) preg_match('/\bA\.?D\.?\b/u', $n) => LegalForm::AD,
            str_contains($n, 'OGRANIČENOM') || (bool) preg_match('/\bD\.?O\.?O\.?\b/u', $n) => LegalForm::DOO,
            str_contains($n, 'PREDUZETNIK') || str_contains($n, 'RADNJA') || (bool) preg_match('/\bPR\b/u', $n) => LegalForm::PREDUZETNIK,
            str_contains($n, 'UDRUŽENJE') || str_contains($n, 'UDRUZENJE') => LegalForm::UDRUZENJE,
            default => LegalForm::OSTALO,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $pib): array
    {
        $client = new \nusoap_client(self::WSDL, 'wsdl');
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;
        if ($error = $client->getError()) {
            throw new \DomainException('NBS servis nije dostupan: '.$error);
        }

        $client->setHeaders(
            '<AuthenticationHeader xmlns="'.self::NAMESPACE.'">'
            .'<UserName>'.htmlspecialchars($this->username, ENT_XML1).'</UserName>'
            .'<Password>'.htmlspecialchars($this->password, ENT_XML1).'</Password>'
            .'<LicenceID>'.htmlspecialchars($this->licence, ENT_XML1).'</LicenceID>'
            .'</AuthenticationHeader>'
        );

        $result = $client->call('GetCompany', [
            'companyID' => '00000000-0000-0000-0000-000000000000',
            'companyCode' => 0,
            'name' => '',
            'city' => '',
            'nationalIdentificationNumber' => 0,
            'taxIdentificationNumber' => $pib,
            'startItemNumber' => 1,
            'endItemNumber' => 100,
        ], self::NAMESPACE);

        if ($client->fault) {
            throw new \DomainException('NBS greška: '.$this->text($result['faultstring'] ?? ''));
        }
        if ($error = $client->getError()) {
            throw new \DomainException('NBS greška: '.$error);
        }

        $xml = is_array($result) ? ($result['GetCompanyResult'] ?? '') : '';
        $xml = is_string($xml) ? trim($xml) : '';
        if ('' === $xml) {
            throw new \DomainException('Firma sa tim PIB-om nije pronađena u NBS.');
        }

        $parsed = @simplexml_load_string($xml);
        if (false === $parsed) {
            throw new \DomainException('NBS je vratio neispravan odgovor.');
        }
        $data = json_decode(json_encode($parsed), true);

        // <NewDataSet><Company>...</Company></NewDataSet>: jedan zapis ili lista
        $company = $data['Company'] ?? $data;
        if (is_array($company) && isset($company[0]) && is_array($company[0])) {
            $match = null;
            foreach ($company as $c) {
                if ($this->text($c['TaxIdentificationNumber'] ?? '') === $pib) {
                    $match = $c;
                    break;
                }
            }
            $company = $match ?? $company[0];
        }
        if (!is_array($company) || '' === $this->text($company['Name'] ?? '')) {
            throw new \DomainException('Firma sa tim PIB-om nije pronađena u NBS.');
        }

        return $company;
    }

    /**
     * Prazan XML element postaje prazan niz, pa se sve vrednosti svode na string.
     */
    private function text(mixed $value): string
    {
        if (is_array($value)) {
            return $value ? trim((string) reset($value)) : '';
        }

        return trim((string) $value);
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
