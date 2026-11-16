<?php

namespace App\Twig;

use App\Service\ImpersonationState;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Baner i dugmad "Prikaži kao korisnik" u šablonima (SPEC 8s). Pravila su u ImpersonationVoter i ImpersonationState, ovde se samo prikazuje.
 */
class ImpersonationExtension extends AbstractExtension
{
    public function __construct(private readonly ImpersonationState $state)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('impersonation', $this->info(...)),
            new TwigFunction('is_impersonating', fn (): bool => $this->state->isActive()),
        ];
    }

    /** @return array{actor: \App\Entity\User, target: \App\Entity\User, mode: \App\Enum\ImpersonationMode, editing: bool}|null */
    public function info(): ?array
    {
        $actor = $this->state->actor();
        $target = $this->state->target();
        if (null === $actor || null === $target) {
            return null;
        }

        return ['actor' => $actor, 'target' => $target, 'mode' => $this->state->mode(), 'editing' => $this->state->isEditing()];
    }
}
