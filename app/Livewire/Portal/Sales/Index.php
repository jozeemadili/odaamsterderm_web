<?php

namespace App\Livewire\Portal\Sales;

use App\Models\SalesRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Layout('layouts.portal', ['title' => 'Sales Report'])]
class Index extends Component
{
    use WithPagination;

    public string $dateFrom;

    public string $dateTo;

    public ?int $expandedSaleId = null;

    public function mount()
    {
        $this->dateTo = now()->toDateString();
        $this->dateFrom = now()->subDays(30)->toDateString();
    }

    public function updatingDateFrom()
    {
        $this->resetPage();
    }

    public function updatingDateTo()
    {
        $this->resetPage();
    }

    public function toggleExpand(int $saleId): void
    {
        $this->expandedSaleId = $this->expandedSaleId === $saleId ? null : $saleId;
    }

    protected function filteredQuery(): Builder
    {
        return SalesRecord::where('merchant_id', Auth::user()->merchant_id)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('sale_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('sale_date', '<=', $this->dateTo))
            ->with('customer')
            ->withCount('items')
            ->latest('sale_date')
            ->latest('id');
    }

    public function exportExcel()
    {
        $sales = $this->filteredQuery()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales report');

        $headers = ['Date', 'Time', 'Customer', 'Items', 'Subtotal', 'Discount', 'Total', 'Payment method'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $row = 2;
        foreach ($sales as $sale) {
            $sheet->fromArray([
                $sale->sale_date->format('d M Y'),
                $sale->created_at->format('H:i'),
                $sale->customer?->name ?? 'Walk-in',
                $sale->items_count,
                (float) ($sale->subtotal ?? $sale->amount),
                (float) $sale->discount_amount,
                (float) $sale->amount,
                ucfirst($sale->payment_method ?? 'cash'),
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'sales-report-'.$this->dateFrom.'-to-'.$this->dateTo.'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf()
    {
        $sales = $this->filteredQuery()->get();
        $merchant = Auth::user()->merchant;

        $pdf = Pdf::loadView('exports.sales-report-pdf', [
            'sales' => $sales,
            'merchant' => $merchant,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'total' => $sales->sum('amount'),
        ])->setPaper('a4', 'portrait');

        $filename = 'sales-report-'.$this->dateFrom.'-to-'.$this->dateTo.'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Buying-value/profit summary for the filtered date range. Only sale
     * lines with a captured unit_cost contribute to buying value / profit —
     * sales recorded before cost tracking started are left out rather than
     * guessed at, and $profitPartial flags when that happened so the UI can
     * note the totals are incomplete.
     */
    protected function costSummary(): array
    {
        $merchantId = Auth::user()->merchant_id;

        $row = DB::table('sale_items')
            ->join('sales_records', 'sales_records.id', '=', 'sale_items.sale_id')
            ->where('sales_records.merchant_id', $merchantId)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('sales_records.sale_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('sales_records.sale_date', '<=', $this->dateTo))
            ->selectRaw('COALESCE(SUM(CASE WHEN sale_items.unit_cost IS NOT NULL THEN sale_items.unit_cost * sale_items.quantity ELSE 0 END), 0) as buying_value')
            ->selectRaw('COALESCE(SUM(CASE WHEN sale_items.unit_cost IS NOT NULL THEN (sale_items.unit_price - sale_items.unit_cost) * sale_items.quantity ELSE 0 END), 0) as profit')
            ->selectRaw('COUNT(sale_items.unit_cost) as cost_lines_count')
            ->selectRaw('COUNT(*) as total_lines_count')
            ->first();

        return [
            'buying_value' => (float) $row->buying_value,
            'selling_value' => (float) $this->filteredQuery()->sum('amount'),
            'profit' => (float) $row->profit,
            'partial' => (int) $row->cost_lines_count < (int) $row->total_lines_count,
        ];
    }

    public function render()
    {
        $sales = $this->filteredQuery()->paginate(10);
        $costSummary = $this->costSummary();

        return view('livewire.portal.sales.index', compact('sales', 'costSummary'));
    }
}
