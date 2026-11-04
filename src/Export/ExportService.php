<?php

namespace App\Export;

use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use League\Csv\Bom;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Environment;

/**
 * Pravi fajl izvoza liste (SPEC 8e): Excel (phpspreadsheet), CSV (league/csv, UTF-8 sa BOM-om, ";") i PDF (dompdf).
 * Veliki izvozi (preko LIMIT_SYNC redova) idu preko Messenger-a (GenerateExportHandler).
 */
class ExportService
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];
    public const LIMIT_SYNC = 2000;

    /** @var array<string, ExportListInterface> */
    private array $lists = [];

    /**
     * @param iterable<ExportListInterface> $lists
     */
    public function __construct(
        #[AutowireIterator('app.export_list')] iterable $lists,
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        foreach ($lists as $list) {
            $this->lists[$list->getName()] = $list;
        }
    }

    public function getList(string $name): ?ExportListInterface
    {
        return $this->lists[$name] ?? null;
    }

    /**
     * Naziv fajla, npr. kontit-klijenti-2026-10-27.xlsx
     */
    public function filename(ExportListInterface $list, string $format): string
    {
        return sprintf('kontit-%s-%s.%s', $list->getName(), date('Y-m-d'), $format);
    }

    /**
     * Upisuje izvoz u fajl i vraća broj redova.
     *
     * @param array<string, mixed> $query filteri iz query stringa liste
     */
    public function write(ExportListInterface $list, string $format, array $query, User $user, string $path): int
    {
        $columns = $list->getColumns();
        $rows = iterator_to_array($list->rows($query, $user), false);

        match ($format) {
            'xlsx' => $this->writeXlsx($list, $columns, $rows, $path),
            'csv' => $this->writeCsv($columns, $rows, $path),
            'pdf' => $this->writePdf($list, $columns, $rows, $list->describeFilters($query, $user), $user, $path),
            default => throw new \InvalidArgumentException('Nepoznat format izvoza.'),
        };

        return \count($rows);
    }

    /**
     * @param list<array{label: string, type: string, width: int}> $columns
     * @param list<list<mixed>>                                     $rows
     */
    private function writeXlsx(ExportListInterface $list, array $columns, array $rows, string $path): void
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('KontIT app')->setTitle($list->getTitle());
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($list->getTitle(), 0, 31));

        foreach ($columns as $i => $column) {
            $letter = $this->letter($i);
            $sheet->setCellValue($letter.'1', $column['label']);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }
        $last = $this->letter(\count($columns) - 1);
        $header = $sheet->getStyle('A1:'.$last.'1');
        $header->getFont()->setBold(true)->getColor()->setRGB('1F2329');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F6F8');
        $header->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D3D8DE');

        foreach ($rows as $r => $row) {
            foreach ($columns as $i => $column) {
                $cell = $this->letter($i).($r + 2);
                $value = $row[$i] ?? null;
                if (null === $value || '' === $value) {
                    continue;
                }
                if ($value instanceof \DateTimeInterface) {
                    $sheet->setCellValue($cell, ExcelDate::PHPToExcel($value));
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('dd.mm.yyyy.');
                } elseif (\in_array($column['type'], ['number', 'money'], true)) {
                    $sheet->setCellValue($cell, (float) $value);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('money' === $column['type'] ? '#,##0.00' : 'General');
                } else {
                    // tekst ostaje tekst (PIB i matični broj sa vodećim nulama)
                    $sheet->setCellValueExplicit($cell, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$last.max(1, \count($rows) + 1));
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    /**
     * @param list<array{label: string, type: string, width: int}> $columns
     * @param list<list<mixed>>                                     $rows
     */
    private function writeCsv(array $columns, array $rows, string $path): void
    {
        // BOM na početku fajla, da Excel prepozna UTF-8 (č, ć, š, ž, đ)
        file_put_contents($path, Bom::Utf8->value);
        $csv = Writer::createFromPath($path, 'a+');
        $csv->setDelimiter(';');
        $csv->insertOne(array_column($columns, 'label'));
        foreach ($rows as $row) {
            $csv->insertOne(array_map(fn ($v, $c) => $this->plain($v, $c['type']), $row, $columns));
        }
    }

    /**
     * @param list<array{label: string, type: string, width: int}> $columns
     * @param list<list<mixed>>                                     $rows
     */
    private function writePdf(ExportListInterface $list, array $columns, array $rows, string $filters, User $user, string $path): void
    {
        $logo = $this->projectDir.'/public/brand/kontit-logo@2x.png';
        $html = $this->twig->render('pdf/list.html.twig', [
            'title' => $list->getTitle(),
            'filters' => $filters,
            'columns' => $columns,
            'rows' => array_map(fn (array $row) => array_map(fn ($v, $c) => $this->plain($v, $c['type']), $row, $columns), $rows),
            'user' => $user,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', \count($columns) > 6 ? 'landscape' : 'portrait');
        $pdf->render();
        file_put_contents($path, $pdf->output());
    }

    private function plain(mixed $value, string $type): string
    {
        return match (true) {
            null === $value => '',
            $value instanceof \DateTimeInterface => $value->format('d.m.Y.'),
            'money' === $type => number_format((float) $value, 2, ',', '.'),
            default => (string) $value,
        };
    }

    private function letter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    }
}
