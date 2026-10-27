<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Podešavanja aplikacije u tabeli app_setting (ključ-vrednost).
 */
class SettingsService
{
    public const MIN_HOURLY_RATE = 'min_hourly_rate';
    public const DEFAULT_CAPACITY_HOURS = 'default_capacity_hours';

    private const DEFAULTS = [
        self::MIN_HOURLY_RATE => '2500',
        self::DEFAULT_CAPACITY_HOURS => '160',
    ];

    public function __construct(
        private readonly AppSettingRepository $settings,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function get(string $key): string
    {
        return $this->settings->find($key)?->getValue() ?? self::DEFAULTS[$key] ?? '';
    }

    public function getFloat(string $key): float
    {
        return (float) $this->get($key);
    }

    public function set(string $key, string $value): void
    {
        $setting = $this->settings->find($key);
        if (null === $setting) {
            $this->em->persist(new AppSetting($key, $value));
        } else {
            $setting->setValue($value);
        }
        $this->em->flush();
    }
}
