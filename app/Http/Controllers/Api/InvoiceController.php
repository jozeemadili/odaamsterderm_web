<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::where('merchant_id', Auth::user()->merchant_id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('issue_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('issue_date', '<=', $request->date('date_to')))
            ->with('customer')
            ->latest('issue_date')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return InvoiceResource::collection($invoices);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $merchantId = Auth::user()->merchant_id;
        $number = 'INV-'.str_pad((string) (Invoice::where('merchant_id', $merchantId)->count() + 1), 5, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            ...$data,
            'merchant_id' => $merchantId,
            'number' => $number,
            'status' => Invoice::STATUS_DRAFT,
            'created_by' => Auth::id(),
        ]);

        return new InvoiceResource($invoice->load('customer'));
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);

        return new InvoiceResource($invoice->load(['items', 'customer', 'payments.paymentMethod']));
    }

    public function addItem(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isDraft(), 403);

        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::where('merchant_id', $invoice->merchant_id)->findOrFail($data['inventory_item_id']);

        $quantity = (float) $data['quantity'];
        $unitPrice = (float) $data['unit_price'];
        $gross = $quantity * $unitPrice;
        $discountType = $data['discount_type'] ?? 'percent';
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountAmount = $this->calculateLineDiscount($unitPrice, $quantity, $discountType, $discountValue);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $item->unit_cost,
            'gross_amount' => $gross,
            'discount_type' => $discountValue > 0 ? $discountType : null,
            'discount_value' => $discountValue > 0 ? $discountValue : null,
            'discount_amount' => $discountAmount,
            'subtotal' => $gross - $discountAmount,
        ]);

        $this->recalculateTotals($invoice);

        return new InvoiceResource($invoice->fresh()->load(['items', 'customer']));
    }

    public function removeItem(Request $request, Invoice $invoice, InvoiceItem $item)
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isDraft(), 403);
        abort_unless($item->invoice_id === $invoice->id, 404);

        $item->delete();
        $this->recalculateTotals($invoice);

        return new InvoiceResource($invoice->fresh()->load(['items', 'customer']));
    }

    public function setDiscount(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isDraft(), 403);

        $data = $request->validate([
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->recalculateTotals($invoice, $data['discount_type'] ?? 'percent', (float) ($data['discount_value'] ?? 0));

        return new InvoiceResource($invoice->fresh()->load(['items', 'customer']));
    }

    public function approve(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($request->user()->can('approve-stock-receipts'), 403);
        abort_unless($invoice->isDraft(), 403);
        abort_if($invoice->items()->count() === 0, 422, 'Add at least one product before approving.');

        DB::transaction(function () use ($invoice, $request) {
            foreach ($invoice->items as $line) {
                if ($line->inventory_item_id) {
                    InventoryItem::where('id', $line->inventory_item_id)->decrement('quantity_on_hand', $line->quantity);

                    StockMovement::create([
                        'merchant_id' => $invoice->merchant_id,
                        'inventory_item_id' => $line->inventory_item_id,
                        'type' => StockMovement::TYPE_OUT,
                        'quantity' => $line->quantity,
                        'reference' => 'invoice',
                        'notes' => "Invoice {$invoice->number}",
                        'movement_date' => now()->toDateString(),
                        'recorded_by' => $request->user()->id,
                    ]);
                }
            }

            $invoice->update([
                'status' => Invoice::STATUS_INVOICED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return new InvoiceResource($invoice->fresh()->load(['items', 'customer']));
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        abort_unless($invoice->isDraft(), 403);

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        return new InvoiceResource($invoice->fresh());
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        abort_if(in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID], true), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['amount'] > $invoice->balanceDue()) {
            return response()->json([
                'message' => 'Amount cannot exceed the balance due (TZS '.number_format($invoice->balanceDue(), 0).').',
                'errors' => ['amount' => ['Amount cannot exceed the balance due.']],
            ], 422);
        }

        DB::transaction(function () use ($invoice, $data, $request) {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'reference' => $data['reference'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            $totalPaid = (float) $invoice->payments()->sum('amount');
            $status = $totalPaid >= (float) $invoice->total
                ? Invoice::STATUS_PAID
                : ($totalPaid > 0 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_INVOICED);

            $invoice->update([
                'amount_paid' => $totalPaid,
                'status' => $status,
                'paid_at' => $status === Invoice::STATUS_PAID ? now() : null,
            ]);
        });

        return new InvoiceResource($invoice->fresh()->load(['items', 'customer', 'payments.paymentMethod']));
    }

    public function pdf(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);

        $merchant = $invoice->merchant;

        $pdf = Pdf::loadView('exports.invoice-pdf', [
            'invoice' => $invoice->load(['items', 'customer', 'payments.paymentMethod']),
            'merchant' => $merchant,
            'logoDataUri' => $merchant->logoDataUri(),
            'brandColor' => $merchant->brandColor(),
            'includeImages' => false,
            'itemImages' => [],
            'qrDataUri' => $invoice->qrDataUri(),
        ])->setPaper('a4', 'portrait');

        $filename = ($invoice->isDraft() ? 'proforma-' : 'invoice-').$invoice->number.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function calculateLineDiscount(float $unitPrice, float $quantity, string $type, float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        $discountedUnit = $type === 'percent' ? $unitPrice * (1 - $value / 100) : $unitPrice - $value;
        $discountedUnit = max(0.0, $discountedUnit);

        return ($unitPrice - $discountedUnit) * $quantity;
    }

    protected function recalculateTotals(Invoice $invoice, ?string $overallType = null, ?float $overallValue = null): void
    {
        $overallType = $overallType ?? $invoice->discount_type ?? 'percent';
        $overallValue = $overallValue ?? (float) ($invoice->discount_value ?? 0);

        $items = $invoice->items()->get();
        $gross = (float) $items->sum('gross_amount');
        $lineDiscount = (float) $items->sum('discount_amount');
        $subtotal = $gross - $lineDiscount;

        $overallDiscount = 0.0;
        if ($overallValue > 0) {
            $overallDiscount = $overallType === 'percent'
                ? $subtotal * ($overallValue / 100)
                : min($overallValue, $subtotal);
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_type' => $overallValue > 0 ? $overallType : null,
            'discount_value' => $overallValue > 0 ? $overallValue : null,
            'discount_amount' => $overallDiscount,
            'total' => max(0, $subtotal - $overallDiscount),
        ]);
    }

    protected function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->merchant_id === $request->user()->merchant_id, 403);
    }
}
