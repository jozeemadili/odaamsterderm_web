<?php

namespace App\Services\CatalogExcel;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

/**
 * Shared spreadsheet plumbing for the catalogue admin pages: a styled
 * data sheet whose dropdowns read from a hidden "Lists" sheet, a "Read me"
 * sheet, row reading by header name, and photos from a ZIP or a web link.
 */
abstract class CatalogSheet
{
    public const LISTS = 'Lists';

    /** Rows that get dropdowns and the fill-in colour. */
    public const ROWS = 1000;

    public const MAX_ROWS = 3000;

    private const BRAND = '5A1019';

    private const FILL_IN = 'FFF9DB';

    private const REQUIRED = 'F8D7DA';

    /** Filename for downloads, e.g. "producers". */
    abstract public function name(): string;

    /** The main sheet's title, also used to find it on import. */
    abstract public function sheetTitle(): string;

    /** A workbook ready to fill in; with $withData, every existing record. */
    abstract public function workbook(bool $withData): Spreadsheet;

    /** Creates or updates records from an uploaded workbook (and optional ZIP of images). */
    abstract public function import(string $xlsxPath, ?string $zipPath, User $by): ImportResult;

    /* ---------- writing ---------- */

    /**
     * @param  list<array{0:string, 1:int, 2?:bool, 3?:string}>  $columns  header, width, required?, note
     */
    protected function dataSheet(Spreadsheet $book, string $title, array $columns, int $index = 0): Worksheet
    {
        $sheet = $index === 0 ? $book->getActiveSheet() : $book->createSheet($index);
        $sheet->setTitle($title);
        $last = Coordinate::stringFromColumnIndex(count($columns));

        foreach ($columns as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$letter}1", $col[0].(! empty($col[2]) ? ' *' : ''));
            $sheet->getColumnDimension($letter)->setWidth($col[1]);
            if (! empty($col[3])) {
                $sheet->getComment("{$letter}1")->setWidth('260pt')->setHeight('90pt')->getText()->createTextRun($col[3]);
            }
            $sheet->getStyle("{$letter}2:{$letter}".self::ROWS)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(! empty($col[2]) ? self::REQUIRED : self::FILL_IN);
        }

        $head = $sheet->getStyle("A1:{$last}1");
        $head->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);
        $head->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle("A1:{$last}".self::ROWS)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D9D9D9');
        $sheet->getStyle("A2:{$last}".self::ROWS)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:{$last}1");
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        return $sheet;
    }

    /**
     * A hidden sheet with one column per list. Returns list name => range for dropdowns.
     *
     * @param  array<string, list<string>>  $lists
     * @return array<string, string>
     */
    protected function listsSheet(Spreadsheet $book, array $lists): array
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(self::LISTS);
        $ranges = [];
        $c = 1;
        foreach ($lists as $name => $values) {
            $letter = Coordinate::stringFromColumnIndex($c++);
            $sheet->setCellValue("{$letter}1", $name);
            foreach (array_values($values) as $i => $value) {
                $sheet->setCellValueExplicit($letter.($i + 2), $value, DataType::TYPE_STRING);
            }
            $ranges[$name] = "'".self::LISTS."'!\${$letter}\$2:\${$letter}\$".max(2, count($values) + 1);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(max(1, $c - 1)).'1')->getFont()->setBold(true);
        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        return $ranges;
    }

    protected function dropdown(Worksheet $sheet, int $column, string $range, string $hint = 'Pick from the list.'): void
    {
        $letter = Coordinate::stringFromColumnIndex($column);
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Not in the list')
            ->setError($hint)
            ->setFormula1($range);
        $sheet->setDataValidation("{$letter}2:{$letter}".self::ROWS, $v);
    }

    protected function wholeNumber(Worksheet $sheet, int $column, int $min = 0, int $max = 100000): void
    {
        $letter = Coordinate::stringFromColumnIndex($column);
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_WHOLE)->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setFormula1((string) $min)->setFormula2((string) $max)
            ->setAllowBlank(true)->setShowErrorMessage(true)
            ->setErrorTitle('Whole number')->setError("Enter a whole number from {$min} to {$max}.");
        $sheet->setDataValidation("{$letter}2:{$letter}".self::ROWS, $v);
    }

    /** @param  list<array{0:string, 1:string}>  $rows */
    protected function readMeSheet(Spreadsheet $book, string $title, array $rows): void
    {
        $sheet = $book->createSheet(0);
        $sheet->setTitle('Read me');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::BRAND);
        $r = 3;
        foreach ($rows as [$k, $v]) {
            $sheet->setCellValue("A{$r}", $k);
            $sheet->setCellValue("B{$r}", $v);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }
        $sheet->setCellValue('A'.($r + 1), 'Colours');
        $sheet->getStyle('A'.($r + 1))->getFont()->setBold(true);
        foreach ([['Pink column (*)', self::REQUIRED, 'Required'], ['Yellow column', self::FILL_IN, 'Optional']] as $i => [$label, $rgb, $meaning]) {
            $row = $r + 2 + $i;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $meaning);
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
        }
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(110);
        $sheet->getStyle("B3:B{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A3:A{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    }

    /** Puts an example row in row 2, greyed and italic, with a note to delete it. */
    protected function exampleRow(Worksheet $sheet, array $values): void
    {
        $sheet->fromArray($values, null, 'A2', true);
        $last = Coordinate::stringFromColumnIndex(count($values));
        $sheet->getStyle("A2:{$last}2")->getFont()->setItalic(true)->getColor()->setRGB('8A8A8A');
        $sheet->getComment('A2')->getText()->createTextRun('Example row. Replace it with your own or delete it. Rows whose Code starts with "EXAMPLE" are skipped.');
    }

    public function stream(Spreadsheet $book, string $filename)
    {
        $book->setActiveSheetIndexByName($this->sheetTitle());
        $writer = IOFactory::createWriter($book, 'Xlsx');

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* ---------- reading ---------- */

    /**
     * Rows of a sheet as row number => [normalised header => value], skipping
     * blank and example rows.
     *
     * @return array<int, array<string, string>>
     */
    protected function rows(string $path, string $sheetTitle, ImportResult $result, bool $fallback = true): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName($sheetTitle);
        if (! $sheet && ! $fallback) {
            return [];
        }
        // A renamed sheet: use the first one that isn't the read-me or lists.
        $sheet ??= collect($book->getAllSheets())->first(fn ($s) => ! in_array($s->getTitle(), ['Read me', self::LISTS], true));
        $data = $sheet->toArray(null, true, false, false);
        $headers = array_map(fn ($h) => self::key((string) $h), array_shift($data) ?? []);

        $rows = [];
        foreach ($data as $i => $values) {
            $row = [];
            foreach ($headers as $c => $h) {
                if ($h !== '') {
                    $row[$h] = trim((string) ($values[$c] ?? ''));
                }
            }
            if (implode('', $row) === '' || Str::startsWith(Str::upper($row['code'] ?? ''), 'EXAMPLE')) {
                continue;
            }
            $rows[$i + 2] = $row;
        }

        if (count($rows) > self::MAX_ROWS) {
            $result->fail(0, 'The file has '.count($rows).' rows; upload at most '.self::MAX_ROWS.' at a time.');

            return [];
        }

        return $rows;
    }

    /** "Product name *" → "product_name" */
    public static function key(string $header): string
    {
        return Str::of($header)->replace('*', '')->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
    }

    protected static function yes(string $value, ?bool $default = null): ?bool
    {
        $v = Str::lower(trim($value));

        return match (true) {
            in_array($v, ['yes', 'y', 'ndiyo', 'true', '1'], true) => true,
            in_array($v, ['no', 'n', 'hapana', 'false', '0'], true) => false,
            default => $default,
        };
    }

    /** "C3020 · Medicines › Dewormers" → "C3020"; plain values pass through. */
    protected static function codeOf(string $value): string
    {
        return trim(Str::before($value, ' · '));
    }

    /* ---------- photos ---------- */

    protected function openZip(?string $zipPath, ImportResult $result): ?ZipArchive
    {
        if (! $zipPath) {
            return null;
        }
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $result->fail(0, 'The photos ZIP could not be opened.');

            return null;
        }

        return $zip;
    }

    /**
     * A local temp file for a photo named in the sheet: a file name inside the
     * ZIP (folders ignored, case-insensitive) or an http(s) link.
     */
    protected function photo(string $ref, ?ZipArchive $zip): ?string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (Str::startsWith(Str::lower($ref), ['http://', 'https://'])) {
            try {
                $response = Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 3]])->get($ref);
            } catch (\Throwable) {
                return null;
            }
            if (! $response->successful() || ! Str::startsWith((string) $response->header('Content-Type'), 'image/') || strlen($response->body()) > 8 * 1024 * 1024) {
                return null;
            }

            return $this->temp($response->body(), Str::afterLast(parse_url($ref, PHP_URL_PATH) ?: 'photo.jpg', '/'));
        }

        if (! $zip) {
            return null;
        }
        $wanted = Str::lower(basename(str_replace('\\', '/', $ref)));
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! Str::endsWith($name, '/') && Str::lower(basename($name)) === $wanted) {
                $bytes = $zip->getFromIndex($i);

                return $bytes === false ? null : $this->temp($bytes, basename($name));
            }
        }

        return null;
    }

    private function temp(string $bytes, string $name): ?string
    {
        if (@getimagesizefromstring($bytes) === false) {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'cat').'-'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.(pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');
        file_put_contents($path, $bytes);

        return $path;
    }
}
