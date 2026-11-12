<?php

namespace App\Service;

use App\Entity\User;

/**
 * Mesečni kapacitet radnika u satima. Prednost: kapacitet korisnika (izuzetak) > kapacitet njegove pozicije > opšti iz podešavanja.
 * Jedino mesto gde se ovo računa (Finansije, analitika opterećenja).
 */
class CapacityResolver
{
    public const SOURCE_USER = 'user';
    public const SOURCE_POSITION = 'position';
    public const SOURCE_DEFAULT = 'default';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function resolve(User $user): float
    {
        return $this->resolveWithSource($user)[0];
    }

    /**
     * Kapacitet koji bi korisnik imao bez sopstvene vrednosti (pozicija, pa opšti). Za prikaz kao placeholder.
     */
    public function inherited(User $user): float
    {
        return (float) ($user->getPosition()?->getMonthlyCapacityHours() ?? $this->settings->getFloat(SettingsService::DEFAULT_CAPACITY_HOURS));
    }

    /**
     * @return array{0: float, 1: string} kapacitet i izvor (SOURCE_*)
     */
    public function resolveWithSource(User $user): array
    {
        if (null !== $user->getCapacityHours()) {
            return [(float) $user->getCapacityHours(), self::SOURCE_USER];
        }
        $position = $user->getPosition()?->getMonthlyCapacityHours();
        if (null !== $position) {
            return [(float) $position, self::SOURCE_POSITION];
        }

        return [(float) $this->settings->getFloat(SettingsService::DEFAULT_CAPACITY_HOURS), self::SOURCE_DEFAULT];
    }
}
