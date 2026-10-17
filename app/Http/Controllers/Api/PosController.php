<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesRecordResource;
use App\Models\DiscountLimit;
use App\Models\InventoryItem;
use App\Models\PaymentMethod;
use App\Models\SaleItem;
use App\Models\SalesRecord;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    /**
     * Checkout a cart of scanned/selected items. Prices are always taken from
     * the current inventory record (never trusted from the client) so a
     * tampered request can't under-charge a sale.
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.discount_type' => ['nullable', 'in:percent,fixed'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'overall_discount_type' => ['nullable', 'in:percent,fixed'],
            'overall_discount_value' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0'],
            'approver_email' => ['nullable', 'email'],
            'approver_password' => ['nullable', 'string'],
        ]);

        $merchantId = $request->user()->merchant_id;

        $items = InventoryItem::where('merchant_id', $merchantId)
            ->whereIn('id', array_column($data['items'], 'inventory_item_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        $gross = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($data['items'] as $row) {
            $item = $items->get($row['inventory_item_id']);

            if (! $item) {
                throw ValidationException::withMessages(['items' => "Product #{$row['inventory_item_id']} was not found."]);
            }

            if ((float) $row['quantity'] > (float) $item->quantity_on_hand) {
                throw ValidationException::withMessages(['items' => "Not enough stock for {$item->name} ({$item->quantity_on_hand} {$item->unit} left)."]);
            }

            $unitPrice = (float) $item->unit_price;
            $discountType = $row['discount_type'] ?? null;
            $discountValue = (float) ($row['discount_value'] ?? 0);

            $discountedUnit = $unitPrice;
            if ($discountType && $discountValue > 0) {
                $discountedUnit = $discountType === 'percent'
                    ? $unitPrice * (1 - $discountValue / 100)
                    : $unitPrice - $discountValue;
                $discountedUnit = max(0.0, $discountedUnit);
            }

            $lineGross = $row['quantity'] * $unitPrice;
            $lineDiscountAmount = ($unitPrice - $discountedUnit) * $row['quantity'];

            $gross += $lineGross;
            $lineDiscountTotal += $lineDiscountAmount;

            $lines[] = [
                'item' => $item,
                'quantity' => (float) $row['quantity'],
                'unit_price' => $unitPrice,
                'unit_cost' => $item->unit_cost,
                'gross' => $lineGross,
                'discount_type' => $discountValue > 0 ? $discountType : null,
                'discount_value' => $discountValue > 0 ? $discountValue : null,
                'discount_amount' => $lineDiscountAmount,
                'subtotal' => $lineGross - $lineDiscountAmount,
            ];
        }

        $subtotal = $gross - $lineDiscountTotal;

        $overallDiscountType = $data['overall_discount_type'] ?? null;
        $overallDiscountValue = (float) ($data['overall_discount_value'] ?? 0);
        $overallDiscount = 0.0;

        if ($overallDiscountType && $overallDiscountValue > 0) {
            $overallDiscount = $overallDiscountType === 'percent'
                ? $subtotal * ($overallDiscountValue / 100)
                : min($overallDiscountValue, $subtotal);
        }

        $total = max(0, $subtotal - $overallDiscount);

        $effectivePercent = $gross > 0 ? round((($lineDiscountTotal + $overallDiscount) / $gross) * 100, 2) : 0.0;

        $user = $request->user();
        $role = $user->roles->first()?->name ?? 'merchant_staff';
        $myLimit = DiscountLimit::maxPercentFor($merchantId, $role);

        $approvedBy = null;

        if ($effectivePercent > $myLimit) {
            if (empty($data['approver_email']) || empty($data['approver_password'])) {
                return response()->json([
                    'needs_approval' => true,
                    'message' => "This discount ({$effectivePercent}%) exceeds your limit ({$myLimit}%). A supervisor or manager must approve it.",
                    'effective_discount_percent' => $effectivePercent,
                    'my_limit_percent' => $myLimit,
                ], 422);
            }

            $approver = User::where('merchant_id', $merchantId)->where('email', $data['approver_email'])->first();

            if (! $approver || ! Hash::check($data['approver_password'], $approver->password)) {
                throw ValidationException::withMessages(['approver_password' => 'Those approver credentials are not valid.']);
            }

            $approverRole = $approver->roles->first()?->name ?? 'merchant_staff';
            $approverLimit = $approverRole === 'merchant_owner' ? 100 : DiscountLimit::maxPercentFor($merchantId, $approverRole);

            if ($approverLimit < $effectivePercent) {
                throw ValidationException::withMessages(['approver_password' => 'This user is not authorized to approve a discount this large.']);
            }

            $approvedBy = $approver->id;
        }

        $paymentMethod = ! empty($data['payment_method_id'])
            ? PaymentMethod::where('merchant_id', $merchantId)->find($data['payment_method_id'])
            : null;

        $tendered = $data['amount_tendered'] ?? $total;

        $sale = DB::transaction(function () use ($merchantId, $data, $lines, $subtotal, $total, $lineDiscountTotal, $overallDiscount, $overallDiscountType, $overallDiscountValue, $approvedBy, $paymentMethod, $tendered, $user) {
            $sale = SalesRecord::create([
                'merchant_id' => $merchantId,
                'customer_id' => $data['customer_id'] ?? null,
                'amount' => $total,
                'subtotal' => $subtotal,
                'discount_type' => $overallDiscountValue > 0 ? ($overallDiscountType ?: 'percent') : null,
                'discount_value' => $overallDiscountValue > 0 ? $overallDiscountValue : null,
                'discount_amount' => $lineDiscountTotal + $overallDiscount,
                'discount_approved_by' => $approvedBy,
                'payment_method' => $paymentMethod?->name ?? 'Cash',
                'payment_method_id' => $paymentMethod?->id,
                'amount_tendered' => $tendered,
                'change_due' => max(0, $tendered - $total),
                'items_count' => array_sum(array_column($lines, 'quantity')),
                'description' => 'Mobile app sale',
                'sale_date' => now()->toDateString(),
                'recorded_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'inventory_item_id' => $line['item']->id,
                    'item_name' => $line['item']->name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $line['unit_cost'],
                    'gross_amount' => $line['gross'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_value'],
                    'discount_amount' => $line['discount_amount'],
                    'subtotal' => $line['subtotal'],
                ]);

                StockMovement::create([
                    'merchant_id' => $merchantId,
                    'inventory_item_id' => $line['item']->id,
                    'type' => StockMovement::TYPE_OUT,
                    'quantity' => $line['quantity'],
                    'reference' => 'sale',
                    'notes' => "Mobile app sale #{$sale->id}",
                    'movement_date' => now()->toDateString(),
                    'recorded_by' => $user->id,
                ]);

                InventoryItem::where('id', $line['item']->id)->decrement('quantity_on_hand', $line['quantity']);
            }

            return $sale;
        });

        return new SalesRecordResource($sale->load(['items', 'customer']));
    }
}
