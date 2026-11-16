<?php

namespace App\Twig;

use App\Entity\TodoItem;
use App\Entity\User;
use App\Enum\TodoRepeat;
use App\Service\ImpersonationState;
use App\Service\TodoService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Podsetnik u šablonima. Vremena i zonu računa isključivo TodoService/TodoClock; Twig samo prikazuje gotove vrednosti.
 */
class TodoExtension extends AbstractExtension
{
    public function __construct(
        private readonly TodoService $todo,
        private readonly Security $security,
        private readonly ImpersonationState $impersonation,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('todo_visible', fn (): bool => !$this->impersonation->isActive()),
            new TwigFunction('todo_count', $this->count(...)),
            new TwigFunction('todo_open_count', $this->openCount(...)),
            new TwigFunction('todo_chips', $this->todo->chips(...)),
            new TwigFunction('todo_fields', $this->todo->formValues(...)),
            new TwigFunction('todo_due', $this->todo->dueLabel(...)),
            new TwigFunction('todo_remind', $this->todo->remindLabel(...)),
            new TwigFunction('todo_overdue', fn (TodoItem $item): bool => !$item->isDone() && 'overdue' === $this->todo->bucket($item)),
            new TwigFunction('todo_repeat_options', static fn (): array => array_map(static fn (TodoRepeat $r): array => ['value' => $r->value, 'label' => $r->label()], TodoRepeat::cases())),
            new TwigFunction('todo_repeats', static fn (): array => TodoRepeat::cases()),
        ];
    }

    /** Broj za topbar: zakasnelo + danas */
    public function count(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User && !$this->impersonation->isActive() ? $this->todo->dueCount($user) : 0;
    }

    /** Da li postoji bilo koja otvorena stavka (tačka na ikonici) */
    public function openCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User && !$this->impersonation->isActive() ? $this->todo->openCount($user) : 0;
    }
}
