<?php

namespace App\Enum;

enum NotificationType: string
{
    case TASK_ASSIGNED = 'TASK_ASSIGNED';
    case TASK_SUBMITTED = 'TASK_SUBMITTED';
    case TASK_APPROVED = 'TASK_APPROVED';
    case TASK_RETURNED = 'TASK_RETURNED';
    case TASK_COMMENTED = 'TASK_COMMENTED';
    case TASK_DUE_SOON = 'TASK_DUE_SOON';
    case TASK_OVERDUE = 'TASK_OVERDUE';
    case CLIENT_ASSIGNED = 'CLIENT_ASSIGNED';
    case BACKUP_ACTIVATED = 'BACKUP_ACTIVATED';
    case TEAM_CHANGED = 'TEAM_CHANGED';
    case ABSENCE_REQUESTED = 'ABSENCE_REQUESTED';
    case ABSENCE_DECIDED = 'ABSENCE_DECIDED';

    /**
     * Naziv za podešavanja u profilu ("uvek šalji i na mail").
     */
    public function label(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED => 'Dodeljen mi je zadatak',
            self::TASK_SUBMITTED => 'Zadatak čeka moju potvrdu',
            self::TASK_APPROVED => 'Moj zadatak je potvrđen',
            self::TASK_RETURNED => 'Zadatak mi je vraćen na doradu',
            self::TASK_COMMENTED => 'Novi komentar na mom zadatku',
            self::TASK_DUE_SOON => 'Rok zadatka uskoro ističe',
            self::TASK_OVERDUE => 'Zadatak kasni',
            self::CLIENT_ASSIGNED => 'Dodeljen mi je klijent',
            self::BACKUP_ACTIVATED => 'Preuzimam klijente kolege (zamena)',
            self::TEAM_CHANGED => 'Promena tima ili šefa',
            self::ABSENCE_REQUESTED => 'Odsustvo čeka moje odobrenje',
            self::ABSENCE_DECIDED => 'Odluka o mom odsustvu',
        };
    }

    /**
     * Phosphor ikonica (klasa bez "ph" baze).
     */
    public function icon(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED => 'list-checks',
            self::TASK_SUBMITTED => 'send',
            self::TASK_APPROVED => 'circle-check',
            self::TASK_RETURNED => 'undo-2',
            self::TASK_COMMENTED => 'message-square-text',
            self::TASK_DUE_SOON => 'clock',
            self::TASK_OVERDUE => 'circle-alert',
            self::CLIENT_ASSIGNED, self::BACKUP_ACTIVATED => 'building-2',
            self::TEAM_CHANGED => 'users',
            self::ABSENCE_REQUESTED, self::ABSENCE_DECIDED => 'calendar-check',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED, self::CLIENT_ASSIGNED, self::TEAM_CHANGED => 'primary',
            self::TASK_SUBMITTED, self::TASK_COMMENTED, self::BACKUP_ACTIVATED => 'info',
            self::ABSENCE_REQUESTED => 'warning',
            self::ABSENCE_DECIDED => 'primary',
            self::TASK_APPROVED => 'success',
            self::TASK_RETURNED, self::TASK_DUE_SOON => 'warning',
            self::TASK_OVERDUE => 'danger',
        };
    }
}
