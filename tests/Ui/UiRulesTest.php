<?php

namespace App\Tests\Ui;

use PHPUnit\Framework\TestCase;

/**
 * Zaštita UI pravila (docs/UI-AUDIT.md, SPEC 8q): dugme je Bootstrap dugme, polje je Bootstrap polje, "Otkaži" samo za poslovne radnje.
 * Skenira templates/**.twig i assets/js/**.js (osim /_design, mailova i PDF-a). Novi izuzetak ide u listu ispod, sa razlogom.
 */
class UiRulesTest extends TestCase
{
    /** Klase koje dugmetu daju izgled: Bootstrap dugme ili naša dokumentovana komponenta (DESIGN.md) */
    private const BUTTON_OK = '/(^|\s)(btn|btn-close|dropdown-item|accordion-button|nav-link|kt-icon-btn|kt-pager-btn|kt-tchip|kt-todo-title|kt-jp-toggle|kt-search-clear|kt-user-toggle|kt-avatar-btn|kt-tab-plus|kt-password-toggle|kt-input-clear|kt-sheet-item|kt-nav-link|kt-tab|kt-tchip-x|kt-mx-cell|kt-mx-count)(\s|$)/';

    /** Tekstualna polja moraju da imaju jednu od ovih klasa (checkbox/radio/hidden/file/submit se proveravaju posebno) */
    private const FIELD_OK = '/(^|\s)(form-control|form-select|form-range|form-check-input|btn-check|kt-input|kt-check|flatpickr-input|swal2-input)(\s|$)/';

    /** "Otkaži" je dozvoljeno samo kao poslovna radnja (otkazivanje zadatka ili odsustva): fajl => razlog */
    private const CANCEL_ALLOWED = [
        'templates/task/show.html.twig' => 'Otkaži zadatak',
        'templates/absence/index.html.twig' => 'Otkaži odsustvo',
        'templates/design/index.html.twig' => '/_design vodič',
    ];

    /** @return list<array{string, string}> */
    private function files(array $extensions): array
    {
        $root = \dirname(__DIR__, 2);
        $found = [];
        foreach (['templates', 'assets/js'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
                $ext = $file->getExtension();
                if (!\in_array($ext, $extensions, true) || preg_match('#^templates/(design|emails|pdf)/#', $rel)) {
                    continue;
                }
                $found[] = [$rel, (string) file_get_contents($file->getPathname())];
            }
        }

        return $found;
    }

    private function attr(string $attrs, string $name): ?string
    {
        return preg_match('/\b'.$name.'="([^"]*)"/', $attrs, $m) ? $m[1] : null;
    }

    private function line(string $src, int $offset): int
    {
        return substr_count($src, "\n", 0, $offset) + 1;
    }

    public function testEveryButtonIsStyled(): void
    {
        $bad = [];
        foreach ($this->files(['twig', 'js']) as [$rel, $src]) {
            if (!preg_match_all('/<button\b([^>]*)>/s', $src, $all, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($all[1] as [$attrs, $offset]) {
                $class = $this->attr($attrs, 'class');
                // klasa koja se slaže u šablonu ({{ ... }}) mora da sadrži bar jedno "btn" u izrazu
                if (null === $class || !preg_match(self::BUTTON_OK, preg_replace('/\{\{.*?\}\}|\{%.*?%\}/s', ' btn ', $class) ?? $class)) {
                    $bad[] = $rel.':'.$this->line($src, $offset).' <button'.substr(trim($attrs), 0, 70);
                }
            }
        }
        self::assertSame([], $bad, "<button> mora da ima klasu btn (ili dokumentovanu komponentu):\n".implode("\n", $bad));
    }

    public function testEveryTextFieldIsStyled(): void
    {
        $bad = [];
        foreach ($this->files(['twig', 'js']) as [$rel, $src]) {
            preg_match_all('/<(input|select|textarea)\b([^>]*)>/s', $src, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            foreach ($all as $m) {
                $attrs = $m[2][0];
                $type = $this->attr($attrs, 'type') ?? 'text';
                if (\in_array($type, ['hidden', 'file', 'submit', 'button', 'reset'], true) || str_contains($attrs, 'data-native-input')) {
                    continue;
                }
                $class = preg_replace('/\{\{.*?\}\}|\{%.*?%\}/s', ' form-control ', $this->attr($attrs, 'class') ?? '') ?? '';
                if (!preg_match(self::FIELD_OK, $class)) {
                    $bad[] = $rel.':'.$this->line($src, $m[0][1]).' <'.$m[1][0].substr(trim($attrs), 0, 70);
                }
            }
        }
        self::assertSame([], $bad, "Polje mora da ima form-control / form-select / form-check-input:\n".implode("\n", $bad));
    }

    public function testCancelWordIsOnlyForBusinessActions(): void
    {
        $bad = [];
        foreach ($this->files(['twig', 'js']) as [$rel, $src]) {
            if (isset(self::CANCEL_ALLOWED[$rel])) {
                continue;
            }
            if (preg_match_all('/>\s*Otkaži\s*<|[\'"]Otkaži[\'"]/u', $src, $all, PREG_OFFSET_CAPTURE)) {
                foreach ($all[0] as [$text, $offset]) {
                    $bad[] = $rel.':'.$this->line($src, $offset).' "Otkaži" (za formu ili prozor treba "Odustani")';
                }
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
    }

    public function testCancelButtonsUseOutlineSecondary(): void
    {
        $bad = [];
        foreach ($this->files(['twig', 'js']) as [$rel, $src]) {
            if (preg_match_all('/class="([^"]*)"[^>]*>\s*Odustani\s*</u', $src, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($all as $m) {
                    if (!str_contains($m[1][0], 'btn-outline-secondary')) {
                        $bad[] = $rel.':'.$this->line($src, $m[0][1]).' Odustani nije btn-outline-secondary';
                    }
                }
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
    }

    /** Vreme se bira samo zajedničkom komponentom (select, 24h, korak 30 min); nativni type=time/datetime-local zavisi od jezika browsera (SPEC 8p.6). */
    public function testNoNativeTimeInputs(): void
    {
        $bad = [];
        foreach ($this->files(['twig', 'js']) as [$rel, $src]) {
            if (preg_match_all('/<input\b[^>]*\btype=["\'](time|datetime-local)["\']/i', $src, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [, $offset]) {
                    $bad[] = $rel.':'.$this->line($src, $offset);
                }
            }
        }
        self::assertSame([], $bad, 'Koristi templates/components/time_select.html.twig ili assets/js/time-select.js umesto type=time/datetime-local: '.implode(', ', $bad));
    }

    /** Bez 12h formata (g:i, h:i, AM/PM, hour12: true) i bez sirovog |date('... H:i') u šablonima: vreme ide kroz kt_time / kt_datetime / kt_datetime_short. */
    public function testNoTwelveHourFormatsAndNoRawTimeDateFilters(): void
    {
        $rules = [
            '/(?:date|format)\(\s*[\'"][^\'"]*(?<![A-Za-z])[gGh]:i/' => '12h format vremena',
            '/(?:date|format)\(\s*[\'"][^\'"]*i\s?[aA][\'"]/' => 'AM/PM u formatu',
            '/\bhour12\s*:\s*true\b/' => 'hour12: true',
            '/\btoLocaleTimeString\(/' => 'toLocaleTimeString (zavisi od jezika browsera)',
            '/\|date\(\s*[\'"][^\'"]*[Hh]:i/' => 'sirovi |date sa vremenom (koristi kt_time / kt_datetime / kt_datetime_short)',
        ];
        $bad = [];
        foreach ($this->files(['twig', 'js']) as [$rel, $src]) {
            foreach ($rules as $regex => $why) {
                if (preg_match_all($regex, $src, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[0] as [, $offset]) {
                        $bad[] = $rel.':'.$this->line($src, $offset).' ('.$why.')';
                    }
                }
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
    }
}
