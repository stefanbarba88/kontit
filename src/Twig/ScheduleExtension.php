<?php

namespace App\Twig;

use App\Entity\Schedule;
use App\Entity\Task;
use App\Service\ScheduleResolver;
use App\Service\ScheduleCalculator;
use App\Form\ScheduleFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * schedule_describe(schedule, short): opis rasporeda rečima (SPEC 8g.3), npr. "Mesečno, 15.".
 * schedule_data(schedule) i schedule_dialog_form(): podaci i polja za prozor "Prilagodi za ovog klijenta" (SPEC 8g.4).
 */
class ScheduleExtension extends AbstractExtension
{
    public function __construct(private readonly ScheduleCalculator $calculator, private readonly FormFactoryInterface $forms, private readonly ScheduleResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('schedule_describe', fn (?Schedule $schedule, bool $short = false): string => null === $schedule ? '' : $this->calculator->describe($schedule, $short)),
            // niz polja rasporeda za JS (podrazumevane i prilagođene vrednosti u redu posla)
            new TwigFunction('schedule_data', static fn (?Schedule $schedule): array => $schedule?->toArray() ?? []),
            // rok koji bi zadatak imao po rasporedu (za oznaku ručno promenjenog roka); samo za zadatke sa ručnim rokom
            new TwigFunction('task_default_due', fn (Task $task): ?\DateTimeImmutable => $task->isDueDateManuallySet() ? $this->resolver->scheduledDue($task) : null),
            // raspored po kome zadatak nastaje (posao klijenta: prilagođeni ili podrazumevani)
            new TwigFunction('task_schedule', fn (Task $task): ?Schedule => $this->resolver->forTask($task)),
            // polja rasporeda (ista zajednička komponenta) za prozor "Prilagodi za ovog klijenta"
            new TwigFunction('schedule_dialog_form', fn () => $this->forms->createNamed('ks', ScheduleFormType::class)->createView()),
        ];
    }
}
