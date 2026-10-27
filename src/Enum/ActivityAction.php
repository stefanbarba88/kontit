<?php

namespace App\Enum;

enum ActivityAction: string
{
    case CREATED = 'CREATED';
    case UPDATED = 'UPDATED';
    case STATUS_CHANGED = 'STATUS_CHANGED';
    case ASSIGNED = 'ASSIGNED';
    case CHECKED = 'CHECKED';
    case UNCHECKED = 'UNCHECKED';
    case COMMENTED = 'COMMENTED';
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case RETURNED = 'RETURNED';
    case TEAM_CHANGED = 'TEAM_CHANGED';
    case DELETED = 'DELETED';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Kreirano',
            self::UPDATED => 'Izmenjeno',
            self::STATUS_CHANGED => 'Promena statusa',
            self::ASSIGNED => 'Promena dodele',
            self::CHECKED => 'Stavka štiklirana',
            self::UNCHECKED => 'Štikliranje poništeno',
            self::COMMENTED => 'Komentar',
            self::SUBMITTED => 'Poslato na proveru',
            self::APPROVED => 'Potvrđeno',
            self::RETURNED => 'Vraćeno na doradu',
            self::TEAM_CHANGED => 'Promena tima',
            self::DELETED => 'Obrisano',
        };
    }

    /**
     * Phosphor ikonica (klasa bez "ph" baze).
     */
    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'ph-plus',
            self::UPDATED => 'ph-pencil',
            self::STATUS_CHANGED => 'ph-arrows-clockwise',
            self::ASSIGNED => 'ph-user-switch',
            self::CHECKED => 'ph-check',
            self::UNCHECKED => 'ph-x',
            self::COMMENTED => 'ph-chat-text',
            self::SUBMITTED => 'ph-paper-plane-tilt',
            self::APPROVED => 'ph-check-circle',
            self::RETURNED => 'ph-arrow-u-up-left',
            self::TEAM_CHANGED => 'ph-users-three',
            self::DELETED => 'ph-trash',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED, self::CHECKED, self::APPROVED => 'success',
            self::UPDATED, self::ASSIGNED, self::TEAM_CHANGED => 'primary',
            self::STATUS_CHANGED, self::SUBMITTED, self::COMMENTED => 'info',
            self::UNCHECKED, self::RETURNED => 'warning',
            self::DELETED => 'danger',
        };
    }
}
