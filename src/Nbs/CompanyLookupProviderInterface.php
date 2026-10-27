<?php

namespace App\Nbs;

/**
 * Izvor podataka o firmi po PIB-u (zvanični NBS veb servis; kasnije može i drugi izvor).
 */
interface CompanyLookupProviderInterface
{
    /** Kratko ime izvora za izbor u .env (NBS_PROVIDER). */
    public function getName(): string;

    public function isConfigured(): bool;

    /**
     * @throws CompanyNotFoundException      firma ne postoji
     * @throws LookupUnavailableException    izvor ne odgovara ili je vratio grešku
     *
     * @return array<string, mixed> isti oblik kao NbsXmlParser::parseCompany(), sa ključem 'accounts'
     */
    public function lookup(string $pib): array;
}
