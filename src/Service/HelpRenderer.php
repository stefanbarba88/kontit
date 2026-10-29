<?php

namespace App\Service;

use App\Enum\HelpGuide;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Uputstva za korisnike (SPEC 8o): Markdown iz docs/uputstvo/{vodič}.md u HTML, uz sadržaj (h2 i h3) za pregled na vrhu.
 * Dogovor u Markdown-u (docs/uputstvo/README.md):
 *   [SLIKA: opis]           mesto za sliku koja još nije ubačena
 *   ![opis](ime-fajla.png)  slika iz docs/uputstvo/slike/ (servira HelpController, samo prijavljenima)
 *   > **Savet:** ...         zeleni okvir; "> **Pažnja:**" je narandžasti okvir
 * Rezultat se kešira po vremenu izmene fajla.
 */
class HelpRenderer
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/docs/uputstvo')] private readonly string $dir,
        private readonly CacheInterface $cache,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array{title: string, html: string, toc: list<array{id: string, text: string, children: list<array{id: string, text: string}>}>}
     */
    public function render(HelpGuide $guide): array
    {
        $file = $this->dir.'/'.$guide->value.'.md';
        if (!is_file($file)) {
            return ['title' => $guide->title(), 'html' => '', 'toc' => []];
        }

        return $this->cache->get(sprintf('help_%s_%d', $guide->value, filemtime($file)), function (ItemInterface $item) use ($file, $guide): array {
            $item->expiresAfter(86400 * 30);

            return $this->convert((string) file_get_contents($file), $guide);
        });
    }

    public function imagePath(string $name): ?string
    {
        $path = $this->dir.'/slike/'.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * @return array{title: string, html: string, toc: list<array{id: string, text: string, children: list<array{id: string, text: string}>}>}
     */
    private function convert(string $markdown, HelpGuide $guide): array
    {
        $environment = new Environment([
            // sadržaj piše administrator aplikacije u repozitorijumu, pa je HTML dozvoljen (npr. <kbd>)
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'insert' => HeadingPermalinkProcessor::INSERT_NONE,
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $html = (string) (new MarkdownConverter($environment))->convert($markdown);

        // naslov dokumenta (h1) ide u zaglavlje strane
        $title = $guide->title();
        $html = (string) preg_replace_callback('~<h1[^>]*>(.*?)</h1>\s*~s', static function (array $m) use (&$title): string {
            $title = trim(strip_tags($m[1]));

            return '';
        }, $html, 1);

        // [SLIKA: opis] → okvir za sliku koja dolazi kasnije
        $html = (string) preg_replace(
            '~<p>\[SLIKA:\s*(.+?)\]</p>~s',
            '<figure class="kt-help-shot is-placeholder" role="img" aria-label="Slika: $1"><span class="kt-help-shot-label">Slika</span><figcaption>$1</figcaption></figure>',
            $html,
        );

        // slika sa relativnom putanjom → iz docs/uputstvo/slike/ kroz kontroler
        $html = (string) preg_replace_callback('~<img src="(?!https?:|/)([A-Za-z0-9._-]+)"~', fn (array $m): string => sprintf('<img loading="lazy" src="%s"', $this->urls->generate('help_image', ['file' => $m[1]])), $html);

        // > **Savet:** / > **Pažnja:** → obojeni okvir
        $html = (string) preg_replace_callback('~<blockquote>\s*<p><strong>(Savet|Pažnja|Važno)(:?)</strong>~u', static fn (array $m): string => sprintf(
            '<blockquote class="kt-help-note is-%s"><p><strong>%s%s</strong>',
            'Savet' === $m[1] ? 'tip' : 'warn',
            $m[1],
            $m[2],
        ), $html);

        // tabele: Bootstrap izgled i horizontalni skrol na telefonu
        $html = str_replace(['<table>', '</table>'], ['<div class="table-responsive"><table class="table table-sm align-middle">', '</table></div>'], $html);

        // svako poglavlje (h2 sa sadržajem do sledećeg h2) u svoj <section>, za pretragu po poglavljima
        $parts = preg_split('~(?=<h2 id=")~', $html) ?: [$html];
        $intro = str_starts_with($parts[0], '<h2 id="') ? '' : array_shift($parts);
        $html = ('' !== trim($intro) ? '<div class="kt-help-intro">'.$intro.'</div>' : '')
            .implode('', array_map(static fn (string $part): string => '<section class="kt-help-section" data-kt-help-section>'.$part.'</section>', $parts));

        $toc = [];
        preg_match_all('~<h([23]) id="([^"]+)">(.*?)</h\1>~s', $html, $headings, \PREG_SET_ORDER);
        foreach ($headings as [, $level, $id, $text]) {
            $entry = ['id' => $id, 'text' => html_entity_decode(trim(strip_tags($text)), \ENT_QUOTES | \ENT_HTML5)];
            if ('2' === $level || [] === $toc) {
                $toc[] = $entry + ['children' => []];
            } else {
                $toc[\count($toc) - 1]['children'][] = $entry;
            }
        }

        return ['title' => $title, 'html' => $html, 'toc' => $toc];
    }
}
