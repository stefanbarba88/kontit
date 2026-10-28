<?php

namespace App\Nbs;

use App\Entity\Client;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Podaci o firmi po PIB-u (SPEC: Client / Učitavanje iz NBS-a).
 * Proverava kontrolnu cifru pre poziva, kešira rezultat 24h po PIB-u i ograničava broj poziva po korisniku.
 * Izvor se bira sa NBS_PROVIDER; ako izabrani nije podešen, koristi se prvi podešen.
 */
class NbsCompanyLookupService
{
    private const CACHE_TTL = 86400;

    /** @var list<CompanyLookupProviderInterface> */
    private array $providers;

    /**
     * @param iterable<CompanyLookupProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.company_lookup_provider')] iterable $providers,
        private readonly string $preferred,
        private readonly CacheItemPoolInterface $cache,
        private readonly RateLimiterFactory $nbsLookupLimiter,
    ) {
        $this->providers = iterator_to_array($providers, false);
    }

    public function isConfigured(): bool
    {
        return null !== $this->provider();
    }

    /**
     * @param string|null $rateKey ključ za ograničenje (npr. id korisnika); null = bez ograničenja (komanda)
     *
     * @throws \DomainException poruka za korisnika
     *
     * @return array<string, mixed>
     */
    public function lookup(string $pib, ?string $rateKey = null): array
    {
        $pib = trim($pib);
        if (!IdentifierChecksum::isValidPib($pib)) {
            throw new \DomainException('PIB nije ispravan (9 cifara sa kontrolnom cifrom).');
        }
        $provider = $this->provider();
        if (null === $provider) {
            throw new \DomainException('NBS pristupni podaci nisu podešeni (NBS_WS_USERNAME, NBS_WS_PASSWORD, NBS_WS_LICENCE u .env.local). Unesi podatke ručno.');
        }

        $item = $this->cache->getItem('nbs_company_'.$pib);
        if ($item->isHit()) {
            $cached = $item->get();

            return $this->withShortName($cached);
        }

        if (null !== $rateKey && !$this->nbsLookupLimiter->create($rateKey)->consume()->isAccepted()) {
            throw new \DomainException('Previše upita ka NBS-u u kratkom roku. Sačekaj minut pa pokušaj ponovo.');
        }

        try {
            $data = $provider->lookup($pib);
        } catch (CompanyNotFoundException) {
            throw new \DomainException('Firma sa tim PIB-om nije pronađena u NBS-u. Unesi podatke ručno.');
        } catch (LookupUnavailableException) {
            throw new \DomainException('NBS trenutno ne odgovara, unesi podatke ručno.');
        }

        $this->cache->save($item->set($data)->expiresAfter(self::CACHE_TTL));

        return $this->withShortName($data);
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
     * shortName je predlog kraćeg naziva (bez pravne forme), a ne kratak naziv iz registra.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withShortName(array $data): array
    {
        return ['shortName' => ShortNameSuggester::suggest($data['name'], $data['shortName'] ?? null)] + $data;
    }

    private function provider(): ?CompanyLookupProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getName() === $this->preferred && $provider->isConfigured()) {
                return $provider;
            }
        }
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                return $provider;
            }
        }

        return null;
    }
}
