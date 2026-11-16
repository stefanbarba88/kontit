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
    case ABSENCE_APPROVED = 'ABSENCE_APPROVED';
    case ABSENCE_REJECTED = 'ABSENCE_REJECTED';
    case ABSENCE_AUTO_APPROVED = 'ABSENCE_AUTO_APPROVED';
    case ABSENCE_UPDATED = 'ABSENCE_UPDATED';
    case APPROVAL_DEADLINE_SOON = 'APPROVAL_DEADLINE_SOON';
    case DOCUMENT_EXPIRING = 'DOCUMENT_EXPIRING';
    case DOCUMENT_ADDED = 'DOCUMENT_ADDED';
    case CALENDAR_SYNC_FAILED = 'CALENDAR_SYNC_FAILED';
    case EXPORT_READY = 'EXPORT_READY';
    case SPECIAL_APPROVAL_SUBMITTED = 'SPECIAL_APPROVAL_SUBMITTED';
    case SPECIAL_APPROVAL_REMINDER = 'SPECIAL_APPROVAL_REMINDER';
    case TODO_REMINDER = 'TODO_REMINDER';

    /**
     * Naziv za podešavanja u profilu ("uvek šalji i na mejl").
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
            self::ABSENCE_APPROVED => 'Moje odsustvo je odobreno',
            self::ABSENCE_REJECTED => 'Moje odsustvo je odbijeno',
            self::ABSENCE_AUTO_APPROVED => 'Odsustvo je automatski odobreno',
            self::ABSENCE_UPDATED => 'Neko mi je izmenio odsustvo',
            self::APPROVAL_DEADLINE_SOON => 'Ističe rok za odobrenje',
            self::DOCUMENT_EXPIRING => 'Dokument uskoro ističe',
            self::DOCUMENT_ADDED => 'Dodat mi je dokument',
            self::CALENDAR_SYNC_FAILED => 'Google kalendar ne može da se osveži',
            self::EXPORT_READY => 'Izvoz je spreman za preuzimanje',
            self::SPECIAL_APPROVAL_SUBMITTED => 'Posebna potvrda čeka moje odobrenje',
            self::SPECIAL_APPROVAL_REMINDER => 'Upozorenje: posebna potvrda čeka duže od 2 radna dana',
            self::TODO_REMINDER => 'Obaveštenje iz podsetnika',
        };
    }

    /**
     * Lucide ikonica (ime za ux_icon('lucide:…')).
     */
    public function icon(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED => 'list-checks',
            self::TODO_REMINDER => 'notebook-pen',
            self::TASK_SUBMITTED => 'send',
            self::TASK_APPROVED => 'badge-check',
            self::TASK_RETURNED => 'undo-2',
            self::TASK_COMMENTED => 'message-square-text',
            self::TASK_DUE_SOON => 'clock',
            self::TASK_OVERDUE => 'circle-alert',
            self::CLIENT_ASSIGNED, self::BACKUP_ACTIVATED => 'building-2',
            self::TEAM_CHANGED => 'users',
            self::ABSENCE_REQUESTED, self::ABSENCE_APPROVED, self::ABSENCE_AUTO_APPROVED, self::ABSENCE_UPDATED => 'calendar-check',
            self::ABSENCE_REJECTED => 'calendar-x',
            self::APPROVAL_DEADLINE_SOON => 'hourglass',
            self::DOCUMENT_EXPIRING, self::DOCUMENT_ADDED => 'file-text',
            self::CALENDAR_SYNC_FAILED => 'cloud-off',
            self::EXPORT_READY => 'download',
            self::SPECIAL_APPROVAL_SUBMITTED, self::SPECIAL_APPROVAL_REMINDER => 'shield-check',
        };
    }

    /** Obaveštenja iz privatnog Podsetnika: ne vide se ni tokom "Prikaži kao korisnik" (SPEC 8s). */
    public function isPrivate(): bool
    {
        return self::TODO_REMINDER === $this;
    }

    public function color(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED, self::CLIENT_ASSIGNED, self::TEAM_CHANGED, self::DOCUMENT_ADDED, self::TODO_REMINDER => 'primary',
            self::TASK_SUBMITTED, self::TASK_COMMENTED, self::BACKUP_ACTIVATED, self::ABSENCE_AUTO_APPROVED, self::ABSENCE_UPDATED => 'info',
            self::ABSENCE_REQUESTED, self::APPROVAL_DEADLINE_SOON, self::DOCUMENT_EXPIRING => 'warning',
            self::TASK_APPROVED, self::EXPORT_READY, self::ABSENCE_APPROVED => 'success',
            self::TASK_RETURNED, self::TASK_DUE_SOON => 'warning',
            self::TASK_OVERDUE, self::ABSENCE_REJECTED, self::CALENDAR_SYNC_FAILED, self::SPECIAL_APPROVAL_REMINDER => 'danger',
            self::SPECIAL_APPROVAL_SUBMITTED => 'warning',
        };
    }

    /**
     * Vrste koje se podrazumevano šalju i na mejl (korisnik može da ih isključi u profilu).
     *
     * @return list<string>
     */
    public static function defaultEmailTypes(): array
    {
        return [self::SPECIAL_APPROVAL_SUBMITTED->value, self::SPECIAL_APPROVAL_REMINDER->value];
    }}
