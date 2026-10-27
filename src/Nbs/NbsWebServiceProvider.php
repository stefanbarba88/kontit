<?php

namespace App\Nbs;

use Psr\Log\LoggerInterface;

/**
 * Zvanični NBS veb servis (članstvo, kredencijali u .env.local: NBS_WS_USERNAME, NBS_WS_PASSWORD, NBS_WS_LICENCE).
 * Podaci o firmi: CoreXmlService.GetCompany; tekući računi: CompanyAccountXmlService.GetCompanyAccount.
 * Dokumentacija: https://webservices.nbs.rs/WebSiteDoc
 */
final class NbsWebServiceProvider implements CompanyLookupProviderInterface
{
    private const BASE = 'https://webservices.nbs.rs/CommunicationOfficeService1_0/';
    private const NS = 'http://communicationoffice.nbs.rs';
    private const TIMEOUT = 8;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $licence,
        private readonly NbsXmlParser $parser,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'webservice';
    }

    public function isConfigured(): bool
    {
        return '' !== $this->username && '' !== $this->password && '' !== $this->licence;
    }

    public function lookup(string $pib): array
    {
        $nil = ' xsi:nil="true" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';

        $xml = $this->call('CoreXmlService.asmx', 'GetCompany',
            '<GetCompany xmlns="'.self::NS.'"><companyID>00000000-0000-0000-0000-000000000000</companyID><companyCode>0</companyCode>'
            .'<name></name><city></city><nationalIdentificationNumber>0</nationalIdentificationNumber>'
            .'<taxIdentificationNumber>'.$pib.'</taxIdentificationNumber><startItemNumber>1</startItemNumber><endItemNumber>100</endItemNumber></GetCompany>');

        try {
            $company = $this->parser->parseCompany($xml, $pib);
        } catch (\UnexpectedValueException $e) {
            throw new LookupUnavailableException($e->getMessage(), 0, $e);
        }
        if (null === $company) {
            throw new CompanyNotFoundException('Firma sa tim PIB-om nije pronađena u NBS-u.');
        }

        // računi nisu presudni: ako ovaj deo ne uspe, firma se i dalje vraća
        try {
            $company['accounts'] = $this->parser->parseAccounts($this->call('CompanyAccountXmlService.asmx', 'GetCompanyAccount',
                '<GetCompanyAccount xmlns="'.self::NS.'"><nationalIdentificationNumber'.$nil.'/><taxIdentificationNumber>'.$pib.'</taxIdentificationNumber>'
                .'<bankCode'.$nil.'/><accountNumber'.$nil.'/><controlNumber'.$nil.'/><companyName></companyName><city></city>'
                .'<startItemNumber>1</startItemNumber><endItemNumber>50</endItemNumber></GetCompanyAccount>'));
        } catch (\Throwable $e) {
            $this->logger->warning('NBS tekući računi nisu preuzeti: {msg}', ['msg' => $e->getMessage(), 'pib' => $pib]);
        }

        return $company;
    }

    private function call(string $service, string $operation, string $body): string
    {
        $client = new \nusoap_client(self::BASE.$service.'?WSDL', 'wsdl', false, false, false, false, self::TIMEOUT, self::TIMEOUT);
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;
        if ($error = $client->getError()) {
            $this->logger->error('NBS WSDL greška: {msg}', ['msg' => $error, 'operation' => $operation]);
            throw new LookupUnavailableException('NBS servis nije dostupan.');
        }
        $client->setHeaders(
            '<AuthenticationHeader xmlns="'.self::NS.'">'
            .'<UserName>'.htmlspecialchars($this->username, ENT_XML1).'</UserName>'
            .'<Password>'.htmlspecialchars($this->password, ENT_XML1).'</Password>'
            .'<LicenceID>'.htmlspecialchars($this->licence, ENT_XML1).'</LicenceID>'
            .'</AuthenticationHeader>'
        );

        $result = $client->call($operation, $body, self::NS, self::NS.'/'.$operation);
        if ($client->fault || ($error = $client->getError())) {
            $message = $client->fault ? (string) ($result['faultstring'] ?? 'fault') : (string) $error;
            $this->logger->error('NBS {op} greška: {msg}', ['op' => $operation, 'msg' => $message]);
            throw new LookupUnavailableException('NBS servis je vratio grešku.');
        }

        $xml = is_array($result) ? ($result[$operation.'Result'] ?? '') : '';

        return is_string($xml) ? $xml : '';
    }
}
