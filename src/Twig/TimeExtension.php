<?php

namespace App\Twig;

use App\Service\TimeFormat;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Vreme u šablonima (SPEC 8p.6): 24h prikaz kroz tri filtera (isto što i |date, u zoni iz twig.date.timezone) i lista
 * vremena za izbor (komponenta templates/components/time_select.html.twig).
 */
class TimeExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%app.time.step_minutes%')]
        private readonly int $step,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('kt_time', fn (Environment $env, mixed $v): string => $this->format($env, $v, TimeFormat::TIME), ['needs_environment' => true]),
            new TwigFilter('kt_datetime', fn (Environment $env, mixed $v): string => $this->format($env, $v, TimeFormat::DATETIME), ['needs_environment' => true]),
            new TwigFilter('kt_datetime_short', fn (Environment $env, mixed $v): string => $this->format($env, $v, TimeFormat::DATETIME_SHORT), ['needs_environment' => true]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('time_step', fn (): int => $this->step),
            new TwigFunction('time_options', fn (?string $existing = null): array => TimeFormat::options($this->step, $existing)),
        ];
    }

    private function format(Environment $env, mixed $value, string $format): string
    {
        /** @var CoreExtension $core */
        $core = $env->getExtension(CoreExtension::class);

        return $core->formatDate($value, $format);
    }
}
