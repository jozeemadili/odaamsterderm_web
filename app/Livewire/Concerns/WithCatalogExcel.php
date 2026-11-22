<?php

namespace App\Livewire\Concerns;

use App\Services\CatalogExcel\AnimalTypeSheet;
use App\Services\CatalogExcel\CatalogSheet;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

/**
 * Excel download/upload for a catalogue admin page: a blank template, all
 * current records, and an upload (with an optional ZIP of images) that
 * reports what it created, updated and could not read.
 */
trait WithCatalogExcel
{
    use WithFileUploads;

    public bool $showExcel = false;

    public $excelFile;

    public $excelZip;

    /** @var array{created:int, updated:int, photos:int, errors:array, warnings:array}|null */
    public ?array $excelResult = null;

    abstract protected function excelSheet(): CatalogSheet;

    public function downloadTemplate()
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $sheet = $this->excelSheet();

        return $sheet->stream($sheet->workbook(false), $sheet->name().'-template.xlsx');
    }

    public function downloadAll()
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $sheet = $this->excelSheet();

        return $sheet->stream($sheet->workbook(true), $sheet->name().'-'.now()->format('Y-m-d').'.xlsx');
    }

    public function importExcel(): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $this->validate([
            'excelFile' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'excelZip' => ['nullable', 'file', 'mimes:zip', 'max:51200'],
        ], [
            'excelFile.required' => 'Choose the Excel file to upload.',
            'excelFile.mimes' => 'Upload the .xlsx file downloaded from this page.',
            'excelZip.mimes' => 'Photos must be in a .zip file.',
        ]);

        @set_time_limit(300);
        $result = $this->excelSheet()->import($this->excelFile->getRealPath(), $this->excelZip?->getRealPath(), Auth::user());
        $this->excelResult = $result->toArray();
        $this->reset(['excelFile', 'excelZip']);
        if (method_exists($this, 'setPage')) {
            $this->setPage(1);
        }
    }

    public function clearExcelResult(): void
    {
        $this->excelResult = null;
    }

    /** "Row 5", "Growth stages row 3", or "File" for problems with the whole file. */
    public function excelRowLabel(int $row): string
    {
        return $row === 0 ? 'File' : AnimalTypeSheet::where($row);
    }
}
