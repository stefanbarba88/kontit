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
    /** Podrazumevan broj dana godišnjeg odmora (SPEC 8j.3) */
    public const ANNUAL_LEAVE_DAYS = 'annual_leave_days';
    /** Rok za automatsko odobravanje odsustva, u radnim satima (SPEC 8m) */
    public const ABSENCE_APPROVAL_HOURS = 'absence_approval_hours';
    /** Automatsko odobravanje potvrde zadatka (NA_PROVERI): podrazumevano isključeno, rok u radnim danima */
    public const TASK_AUTO_APPROVE = 'task_auto_approve';
    public const TASK_AUTO_APPROVE_DAYS = 'task_auto_approve_days';
    /** Google kalendar uključen za firmu i da li šefovi dobijaju odsustva svog tima (SPEC 8n.3) */
    public const GOOGLE_ENABLED = 'google_enabled';
    public const GOOGLE_LEADER_TEAM = 'google_leader_team';

    private const DEFAULTS = [
        self::MIN_HOURLY_RATE => '2500',
        self::DEFAULT_CAPACITY_HOURS => '160',
        self::ANNUAL_LEAVE_DAYS => '20',
        self::ABSENCE_APPROVAL_HOURS => '48',
        self::TASK_AUTO_APPROVE => '0',
        self::TASK_AUTO_APPROVE_DAYS => '3',
        self::GOOGLE_ENABLED => '1',
        self::GOOGLE_LEADER_TEAM => '0',
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
