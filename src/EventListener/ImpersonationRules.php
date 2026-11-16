<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Request;

/**
 * Pravila impersonacije (SPEC 8s) kao čista logika bez sesije i tokena, da se lako testiraju.
 * Jedino mesto gde su nabrojane rute: privatne (404) i zabranjene čak i u režimu izmena. Voteri su druga linija odbrane
 * (TaskVoter, AbsenceVoter, UserDocumentVoter, UserVoter, TodoVoter odbijaju SwitchUserToken), a ovde se zahtev zaustavlja pre kontrolera.
 */
final class ImpersonationRules
{
    public const VIEW_ONLY = 'Samo pregled: izmene nisu dozvoljene.';
    public const FORBIDDEN = 'Ova radnja nije dozvoljena u ime korisnika.';

    /** Zabranjeno i u režimu izmena (nikad u tuđe ime). Ključ je ime rute, vrednost razlog (za dokumentaciju). */
    public const FORBIDDEN_ROUTES = [
        // lozinka, mejl i lični podaci naloga
        'app_profile' => 'profil: lozinka, mejl, lični podaci',
        'app_forgot_password_request' => 'lozinka',
        'app_reset_password' => 'lozinka',
        // odluke: potvrde i odobravanja
        'task_approve' => 'potvrda zadatka i posebna potvrda',
        'task_return' => 'vraćanje zadatka na doradu',
        'task_cancel' => 'otkazivanje zadatka',
        'client_job_approve' => 'odobravanje predloženog posla',
        // govor u tuđe ime
        'ajax_comment_add' => 'komentar na zadatku',
        'ajax_comment_edit' => 'izmena komentara',
        // privatni podaci
        'user_documents_upload' => 'dokumenti korisnika',
        'user_document_action' => 'dokumenti korisnika',
        'connect_google' => 'Google kalendar',
        'connect_google_check' => 'Google kalendar',
        'profile_google_disconnect' => 'Google kalendar',
        'profile_ics' => 'ICS link',
        // impersonacija
        'impersonation_switch' => 'impersonacija',
    ];

    /** Prefiksi ruta zabranjenih u režimu izmena: korisnici (rola, pozicija, deaktivacija, brisanje) i pozicije. */
    public const FORBIDDEN_PREFIXES = ['admin_user_', 'admin_position_'];

    /** Akcije odsustva koje su odluke (otkazivanje sopstvenog zahteva nije): odobri, odbij, poništi automatsko odobrenje. */
    public const ABSENCE_DECISIONS = ['approve', 'reject', 'revoke'];

    /** GET rute koje bi nešto promenile (povezivanje naloga preko OAuth-a): zabranjene u oba režima. */
    public const FORBIDDEN_READS = ['connect_google', 'connect_google_check'];

    /** Podsetnik: strogo privatan, za vreme impersonacije ne postoji (404). */
    public function isPrivate(string $route): bool
    {
        return str_starts_with($route, 'todo_') || str_starts_with($route, 'ajax_todo');
    }

    public function forbidsRead(string $route): bool
    {
        return \in_array($route, self::FORBIDDEN_READS, true);
    }

    /** Da li je radnja zabranjena i u režimu izmena. */
    public function isNeverAllowed(string $route, Request $request): bool
    {
        if (isset(self::FORBIDDEN_ROUTES[$route])) {
            return true;
        }
        foreach (self::FORBIDDEN_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return 'absence_action' === $route && \in_array((string) $request->attributes->get('action'), self::ABSENCE_DECISIONS, true);
    }

    /**
     * Poruka zašto je zahtev zabranjen, ili null ako sme.
     * Poziva se za izmene (POST/PUT/PATCH/DELETE) i za rute iz forbidsRead().
     */
    public function denial(string $route, Request $request, bool $editing): ?string
    {
        if ($this->forbidsRead($route) || ($editing && $this->isNeverAllowed($route, $request))) {
            return self::FORBIDDEN;
        }

        return $editing ? null : self::VIEW_ONLY;
    }
}
