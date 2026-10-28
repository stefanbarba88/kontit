<?php

namespace App\Twig;

use App\Entity\GoogleCalendarConnection;
use App\Entity\User;
use App\Google\CalendarSync;
use App\Repository\GoogleCalendarConnectionRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * google_calendar_active() i google_connection(user): kartica "Google kalendar" na profilu (SPEC 8n.3).
 */
class GoogleExtension extends AbstractExtension
{
    public function __construct(private readonly CalendarSync $sync, private readonly GoogleCalendarConnectionRepository $connections)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('google_calendar_active', fn (): bool => $this->sync->isActive()),
            new TwigFunction('google_connection', fn (User $user): ?GoogleCalendarConnection => $this->connections->findOneBy(['user' => $user])),
        ];
    }
}
