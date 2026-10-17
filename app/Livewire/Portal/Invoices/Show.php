<?php

namespace App\Livewire\Portal\Invoices;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\PaymentMethod;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Invoice'])]
class Show extends Component
{
    public Invoice $invoice;

    // Add-item form
    public string $productSearch = '';

    public ?int $selectedItemId = null;

    public ?string $selectedItemLabel = null;

    /** @var array<int, array{id:int,name:string,quantity_on_hand:string,unit:?string,unit_price:?string}> */
    public array $productMatches = [];

    public string $quantity = '1';

    public string $unit_price = '';

    public string $lineDiscountType = 'percent';

    public string $lineDiscountValue = '';

    // Inline edit
    public ?int $editingItemId = null;

    public string $edit_quantity = '';

    public string $edit_unit_price = '';

    public string $edit_discount_type = 'percent';

    public string $edit_discount_value = '';

    // Overall discount
    public string $overallDiscountType = '';

    public string $overallDiscountValue = '';

    // Payment
    public string $paymentAmount = '';

    public string $paymentMethodId = '';

    public string $paymentDate;

    public string $paymentReference = '';

    public bool $includeImages = false;

    public function mount(Invoice $invoice)
    {
        abort_unless($invoice->merchant_id === Auth::user()->merchant_id, 403);
        $this->invoice = $invoice;
        $this->overallDiscountType = $invoice->discount_type ?? '';
        $this->overallDiscountValue = $invoice->discount_value ? (string) $invoice->discount_value : '';
        $this->paymentDate = now()->toDateString();
    }

    // --- Line items (draft only) ---------------------------------------

    public function updatedProductSearch(string $value): void
    {
        $trimmed = trim($value);
        $this->selectedItemId = null;
        $this->selectedItemLabel = null;

        if (mb_strlen($trimmed) < 1) {
            $this->productMatches = [];

            return;
        }

        $this->productMatches = InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->where('name', 'like', "%{$trimmed}%")
            ->limit(8)
            ->get(['id', 'name', 'quantity_on_hand', 'unit', 'unit_price'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'quantity_on_hand' => $i->quantity_on_hand,
                'unit' => $i->unit,
                'unit_price' => $i->unit_price,
            ])
            ->all();
    }

    public function selectProduct(int $itemId): void
    {
        $match = collect($this->productMatches)->firstWhere('id', $itemId);

        if (! $match) {
            return;
        }

        $this->selectedItemId = $itemId;
        $this->selectedItemLabel = $match['name'];
        $this->unit_price = $match['unit_price'] ? (string) $match['unit_price'] : '';
        $this->productSearch = '';
        $this->productMatches = [];
    }

    public function clearSelectedProduct(): void
    {
        $this->selectedItemId = null;
        $this->selectedItemLabel = null;
        $this->productSearch = '';
        $this->productMatches = [];
    }

    public function addItem(): void
    {
        abort_unless($this->invoice->isDraft(), 403);

        $this->validate([
            'selectedItemId' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'lineDiscountValue' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::where('merchant_id', Auth::user()->merchant_id)->findOrFail($this->selectedItemId);

        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;
        $gross = $quantity * $unitPrice;
        $discountValue = (float) ($this->lineDiscountValue ?: 0);
        $discountAmount = $this->calculateLineDiscount($unitPrice, $quantity, $this->lineDiscountType, $discountValue);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $gross,
            'discount_type' => $discountValue > 0 ? $this->lineDiscountType : null,
            'discount_value' => $discountValue > 0 ? $discountValue : null,
            'discount_amount' => $discountAmount,
            'subtotal' => $gross - $discountAmount,
        ]);

        $this->reset(['selectedItemId', 'selectedItemLabel', 'productSearch', 'productMatches', 'quantity', 'unit_price', 'lineDiscountType', 'lineDiscountValue']);
        $this->quantity = '1';
        $this->lineDiscountType = 'percent';

        $this->recalculateTotals();
    }

    public function startEditItem(int $itemId): void
    {
        $line = $this->invoice->items()->findOrFail($itemId);

        $this->editingItemId = $itemId;
        $this->edit_quantity = (string) $line->quantity;
        $this->edit_unit_price = (string) $line->unit_price;
        $this->edit_discount_type = $line->discount_type ?? 'percent';
        $this->edit_discount_value = $line->discount_value ? (string) $line->discount_value : '';
    }

    public function cancelEditItem(): void
    {
        $this->editingItemId = null;
    }

    public function saveEditItem(): void
    {
        abort_unless($this->invoice->isDraft(), 403);

        $this->validate([
            'edit_quantity' => ['required', 'numeric', 'min:0.01'],
            'edit_unit_price' => ['required', 'numeric', 'min:0'],
            'edit_discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $line = $this->invoice->items()->findOrFail($this->editingItemId);

        $quantity = (float) $this->edit_quantity;
        $unitPrice = (float) $this->edit_unit_price;
        $gross = $quantity * $unitPrice;
        $discountValue = (float) ($this->edit_discount_value ?: 0);
        $discountAmount = $this->calculateLineDiscount($unitPrice, $quantity, $this->edit_discount_type, $discountValue);

        $line->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $gross,
            'discount_type' => $discountValue > 0 ? $this->edit_discount_type : null,
            'discount_value' => $discountValue > 0 ? $discountValue : null,
            'discount_amount' => $discountAmount,
            'subtotal' => $gross - $discountAmount,
        ]);

        $this->editingItemId = null;
        $this->recalculateTotals();
    }

    public function removeItem(int $itemId): void
    {
        abort_unless($this->invoice->isDraft(), 403);

        $this->invoice->items()->where('id', $itemId)->delete();
        $this->recalculateTotals();
    }

    public function updatedOverallDiscountValue(): void
    {
        $this->recalculateTotals();
    }

    public function updatedOverallDiscountType(): void
    {
        $this->recalculateTotals();
    }

    protected function calculateLineDiscount(float $unitPrice, float $quantity, string $type, float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        $discountedUnit = $type === 'percent'
            ? $unitPrice * (1 - ($value / 100))
            : $unitPrice - $value;

        $discountedUnit = max(0.0, $discountedUnit);

        return ($unitPrice - $discountedUnit) * $quantity;
    }

    protected function recalculateTotals(): void
    {
        $items = $this->invoice->items()->get();
        $gross = (float) $items->sum('gross_amount');
        $lineDiscount = (float) $items->sum('discount_amount');
        $subtotal = $gross - $lineDiscount;

        $overallValue = (float) ($this->overallDiscountValue ?: 0);
        $overallDiscount = 0.0;
        if ($overallValue > 0) {
            $overallDiscount = $this->overallDiscountType === 'percent'
                ? $subtotal * ($overallValue / 100)
                : min($overallValue, $subtotal);
        }

        $total = max(0, $subtotal - $overallDiscount);

        $this->invoice->update([
            'subtotal' => $subtotal,
            'discount_type' => $overallValue > 0 ? ($this->overallDiscountType ?: 'percent') : null,
            'discount_value' => $overallValue > 0 ? $overallValue : null,
            'discount_amount' => $overallDiscount,
            'total' => $total,
        ]);

        $this->invoice->refresh();
    }

    // --- Approve ---------------------------------------------------------

    public function approve(): void
    {
        abort_unless(Auth::user()->can('approve-stock-receipts'), 403);
        abort_unless($this->invoice->isDraft(), 403);
        abort_if($this->invoice->items()->count() === 0, 422);

        DB::transaction(function () {
            foreach ($this->invoice->items as $line) {
                if ($line->inventory_item_id) {
                    InventoryItem::where('id', $line->inventory_item_id)->decrement('quantity_on_hand', $line->quantity);

                    StockMovement::create([
                        'merchant_id' => $this->invoice->merchant_id,
                        'inventory_item_id' => $line->inventory_item_id,
                        'type' => StockMovement::TYPE_OUT,
                        'quantity' => $line->quantity,
                        'reference' => 'invoice',
                        'notes' => "Invoice {$this->invoice->number}",
                        'movement_date' => now()->toDateString(),
                        'recorded_by' => Auth::id(),
                    ]);
                }
            }

            $this->invoice->update([
                'status' => Invoice::STATUS_INVOICED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        session()->flash('status', 'Invoice approved and sent. Inventory has been updated.');
    }

    public function cancel(): void
    {
        abort_unless($this->invoice->isDraft(), 403);

        $this->invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        session()->flash('status', 'Proforma cancelled.');
    }

    // --- Payments ----------------------------------------------------------

    public function recordPayment(): void
    {
        abort_if(in_array($this->invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID], true), 403);

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethodId' => ['nullable', 'exists:payment_methods,id'],
            'paymentDate' => ['required', 'date'],
        ]);

        $amount = (float) $this->paymentAmount;
        $balance = $this->invoice->balanceDue();

        if ($amount > $balance) {
            $this->addError('paymentAmount', 'Amount cannot exceed the balance due (TZS '.number_format($balance, 0).').');

            return;
        }

        DB::transaction(function () use ($amount) {
            InvoicePayment::create([
                'invoice_id' => $this->invoice->id,
                'payment_method_id' => $this->paymentMethodId ?: null,
                'amount' => $amount,
                'payment_date' => $this->paymentDate,
                'reference' => $this->paymentReference ?: null,
                'recorded_by' => Auth::id(),
            ]);

            $totalPaid = (float) $this->invoice->payments()->sum('amount');
            $status = $totalPaid >= (float) $this->invoice->total
                ? Invoice::STATUS_PAID
                : ($totalPaid > 0 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_INVOICED);

            $this->invoice->update(['amount_paid' => $totalPaid, 'status' => $status]);
        });

        $this->invoice->refresh();
        $this->reset(['paymentAmount', 'paymentReference']);
        $this->paymentDate = now()->toDateString();
        session()->flash('status', $this->invoice->isFullyPaid() ? 'Payment recorded. Invoice is fully paid.' : 'Payment recorded.');
    }

    // --- PDF -----------------------------------------------------------

    public function downloadPdf()
    {
        $merchant = Auth::user()->merchant;

        $pdf = Pdf::loadView('exports.invoice-pdf', [
            'invoice' => $this->invoice->load(['items', 'customer', 'payments.paymentMethod']),
            'merchant' => $merchant,
            'logoDataUri' => $merchant->logoDataUri(),
            'brandColor' => $merchant->brandColor(),
            'includeImages' => $this->includeImages,
            'itemImages' => $this->includeImages ? $this->itemImagesDataUris() : [],
            'qrDataUri' => $this->invoice->qrDataUri(),
        ])->setPaper('a4', 'portrait');

        $filename = ($this->invoice->isDraft() ? 'proforma-' : 'invoice-').$this->invoice->number.'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** @return array<int, string> invoice item id => data URI */
    protected function itemImagesDataUris(): array
    {
        $map = [];

        foreach ($this->invoice->items()->with('inventoryItem')->get() as $line) {
            $media = $line->inventoryItem?->image();

            if (! $media) {
                continue;
            }

            $path = $media->getPath();

            if (! is_file($path)) {
                continue;
            }

            $map[$line->id] = 'data:'.$media->mime_type.';base64,'.base64_encode(file_get_contents($path));
        }

        return $map;
    }

    public function render()
    {
        $this->invoice->refresh();

        return view('livewire.portal.invoices.show', [
            'lines' => $this->invoice->items()->with('inventoryItem')->get(),
            'payments' => $this->invoice->payments()->with(['paymentMethod', 'recordedBy'])->latest()->get(),
            'paymentMethods' => PaymentMethod::where('merchant_id', $this->invoice->merchant_id)->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
