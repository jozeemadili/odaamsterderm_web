<?php

namespace App\Livewire\Portal\Pos;

use App\Models\Customer;
use App\Models\DiscountLimit;
use App\Models\InventoryItem;
use App\Models\PaymentMethod;
use App\Models\SaleItem;
use App\Models\SalesRecord;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Point of Sale'])]
class Index extends Component
{
    public string $search = '';

    /**
     * cart[itemId] = ['name','unit','unit_price','quantity','max','discount_type','discount_value']
     */
    public array $cart = [];

    public ?string $lastReceiptTotal = null;

    // Payment
    public ?int $paymentMethodId = null;

    public string $amountTendered = '';

    // Customer
    public ?int $customerId = null;

    public ?string $customerLabel = null;

    public bool $showCustomerPicker = false;

    public string $customerSearch = '';

    public bool $showNewCustomerForm = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    // Per-line discount editor
    public ?int $discountingItemId = null;

    public string $lineDiscountType = 'percent';

    public string $lineDiscountValue = '';

    // Overall sale discount
    public string $overallDiscountType = '';

    public string $overallDiscountValue = '';

    // Manager/supervisor override for over-limit discounts
    public ?int $discountApprovedBy = null;

    public ?string $discountApprovedByName = null;

    public bool $showOverridePanel = false;

    public string $overrideEmail = '';

    public string $overridePassword = '';

    public string $checkoutError = '';

    public function mount(): void
    {
        $this->paymentMethodId = PaymentMethod::where('merchant_id', Auth::user()->merchant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('id');
    }

    public function addToCart(int $itemId): void
    {
        $item = InventoryItem::where('merchant_id', Auth::user()->merchant_id)->findOrFail($itemId);

        $inCart = $this->cart[$itemId]['quantity'] ?? 0;

        if ($inCart >= (float) $item->quantity_on_hand) {
            return;
        }

        $this->cart[$itemId] = [
            'name' => $item->name,
            'unit' => $item->unit,
            'unit_price' => (float) $item->unit_price,
            'quantity' => $inCart + 1,
            'max' => (float) $item->quantity_on_hand,
            'discount_type' => $this->cart[$itemId]['discount_type'] ?? null,
            'discount_value' => $this->cart[$itemId]['discount_value'] ?? 0,
        ];

        $this->clearDiscountApproval();
    }

    public function incrementQty(int $itemId): void
    {
        if (! isset($this->cart[$itemId])) {
            return;
        }

        if ($this->cart[$itemId]['quantity'] < $this->cart[$itemId]['max']) {
            $this->cart[$itemId]['quantity']++;
        }

        $this->clearDiscountApproval();
    }

    public function decrementQty(int $itemId): void
    {
        if (! isset($this->cart[$itemId])) {
            return;
        }

        $this->cart[$itemId]['quantity']--;

        if ($this->cart[$itemId]['quantity'] <= 0) {
            unset($this->cart[$itemId]);
        }

        $this->clearDiscountApproval();
    }

    public function removeFromCart(int $itemId): void
    {
        unset($this->cart[$itemId]);
        $this->clearDiscountApproval();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->resetDiscounts();
        $this->resetCustomer();
        $this->amountTendered = '';
    }

    public function changeDue(): float
    {
        if ($this->amountTendered === '') {
            return 0.0;
        }

        return max(0.0, (float) $this->amountTendered - $this->totals()['total']);
    }

    public function amountShort(): float
    {
        if ($this->amountTendered === '') {
            return 0.0;
        }

        return max(0.0, $this->totals()['total'] - (float) $this->amountTendered);
    }

    // --- Discounts -----------------------------------------------------

    public function startLineDiscount(int $itemId): void
    {
        if (! isset($this->cart[$itemId])) {
            return;
        }

        $this->discountingItemId = $itemId;
        $this->lineDiscountType = $this->cart[$itemId]['discount_type'] ?? 'percent';
        $this->lineDiscountValue = $this->cart[$itemId]['discount_value'] ? (string) $this->cart[$itemId]['discount_value'] : '';
    }

    public function saveLineDiscount(): void
    {
        if (! $this->discountingItemId || ! isset($this->cart[$this->discountingItemId])) {
            return;
        }

        $this->validate([
            'lineDiscountValue' => ['nullable', 'numeric', 'min:0'],
        ]);

        $value = (float) ($this->lineDiscountValue ?: 0);

        $this->cart[$this->discountingItemId]['discount_type'] = $value > 0 ? $this->lineDiscountType : null;
        $this->cart[$this->discountingItemId]['discount_value'] = $value;

        $this->discountingItemId = null;
        $this->clearDiscountApproval();
    }

    public function clearLineDiscount(int $itemId): void
    {
        if (! isset($this->cart[$itemId])) {
            return;
        }

        $this->cart[$itemId]['discount_type'] = null;
        $this->cart[$itemId]['discount_value'] = 0;
        $this->discountingItemId = null;
        $this->clearDiscountApproval();
    }

    public function updatedOverallDiscountValue(): void
    {
        $this->clearDiscountApproval();
    }

    public function updatedOverallDiscountType(): void
    {
        $this->clearDiscountApproval();
    }

    protected function resetDiscounts(): void
    {
        $this->overallDiscountType = '';
        $this->overallDiscountValue = '';
        $this->discountingItemId = null;
        $this->clearDiscountApproval();
    }

    protected function clearDiscountApproval(): void
    {
        $this->discountApprovedBy = null;
        $this->discountApprovedByName = null;
        $this->showOverridePanel = false;
        $this->checkoutError = '';
    }

    /**
     * @return array{gross: float, lineDiscount: float, subtotal: float, overallDiscount: float, total: float}
     */
    public function totals(): array
    {
        $gross = 0.0;
        $lineDiscount = 0.0;

        foreach ($this->cart as $line) {
            $lineGross = $line['quantity'] * $line['unit_price'];
            $gross += $lineGross;
            $lineDiscount += $this->lineDiscountAmount($line);
        }

        $subtotal = $gross - $lineDiscount;

        $overallDiscount = 0.0;
        if ($this->overallDiscountValue !== '' && (float) $this->overallDiscountValue > 0) {
            $overallDiscount = $this->overallDiscountType === 'percent'
                ? $subtotal * ((float) $this->overallDiscountValue / 100)
                : min((float) $this->overallDiscountValue, $subtotal);
        }

        $total = max(0, $subtotal - $overallDiscount);

        return [
            'gross' => $gross,
            'lineDiscount' => $lineDiscount,
            'subtotal' => $subtotal,
            'overallDiscount' => $overallDiscount,
            'total' => $total,
        ];
    }

    /**
     * The item's selling price after its per-unit discount is applied.
     * Fixed discounts are a per-unit amount (e.g. "$2 off each unit"), the
     * same way a percentage discount reduces the per-unit price.
     */
    public function discountedUnitPrice(array $line): float
    {
        if (empty($line['discount_type']) || empty($line['discount_value'])) {
            return (float) $line['unit_price'];
        }

        $discounted = $line['discount_type'] === 'percent'
            ? $line['unit_price'] * (1 - ((float) $line['discount_value'] / 100))
            : $line['unit_price'] - (float) $line['discount_value'];

        return max(0.0, $discounted);
    }

    public function lineDiscountAmount(array $line): float
    {
        $perUnitDiscount = (float) $line['unit_price'] - $this->discountedUnitPrice($line);

        return $perUnitDiscount * (float) $line['quantity'];
    }

    public function effectiveDiscountPercent(): float
    {
        $totals = $this->totals();

        if ($totals['gross'] <= 0) {
            return 0.0;
        }

        $totalDiscount = $totals['lineDiscount'] + $totals['overallDiscount'];

        return round(($totalDiscount / $totals['gross']) * 100, 2);
    }

    public function myDiscountLimit(): float
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name ?? 'merchant_staff';

        return DiscountLimit::maxPercentFor($user->merchant_id, $role);
    }

    public function needsDiscountApproval(): bool
    {
        return $this->effectiveDiscountPercent() > $this->myDiscountLimit() && ! $this->discountApprovedBy;
    }

    public function requestOverride(): void
    {
        $this->showOverridePanel = true;
        $this->overrideEmail = '';
        $this->overridePassword = '';
        $this->checkoutError = '';
    }

    public function authorizeOverride(): void
    {
        $this->validate([
            'overrideEmail' => ['required', 'email'],
            'overridePassword' => ['required', 'string'],
        ]);

        $merchantId = Auth::user()->merchant_id;
        $approver = User::where('merchant_id', $merchantId)
            ->where('email', $this->overrideEmail)
            ->first();

        if (! $approver || ! Hash::check($this->overridePassword, $approver->password)) {
            $this->addError('overridePassword', 'Those credentials are not valid.');

            return;
        }

        $approverRole = $approver->roles->first()?->name ?? 'merchant_staff';
        $approverLimit = $approverRole === 'merchant_owner' ? 100 : DiscountLimit::maxPercentFor($merchantId, $approverRole);

        if ($approverLimit < $this->effectiveDiscountPercent()) {
            $this->addError('overridePassword', 'This user is not authorized to approve a discount this large.');

            return;
        }

        $this->discountApprovedBy = $approver->id;
        $this->discountApprovedByName = $approver->name;
        $this->showOverridePanel = false;
        $this->overrideEmail = '';
        $this->overridePassword = '';
        $this->checkoutError = '';
    }

    // --- Customer --------------------------------------------------------

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::where('merchant_id', Auth::user()->merchant_id)->findOrFail($customerId);

        $this->customerId = $customer->id;
        $this->customerLabel = $customer->name.($customer->phone ? " ({$customer->phone})" : '');
        $this->showCustomerPicker = false;
        $this->customerSearch = '';
    }

    public function useWalkIn(): void
    {
        $this->resetCustomer();
        $this->showCustomerPicker = false;
    }

    protected function resetCustomer(): void
    {
        $this->customerId = null;
        $this->customerLabel = null;
        $this->customerSearch = '';
        $this->showNewCustomerForm = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
    }

    public function createCustomer(): void
    {
        $this->validate([
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newCustomerPhone' => ['nullable', 'string', 'max:30'],
        ]);

        $merchantId = Auth::user()->merchant_id;
        $count = Customer::where('merchant_id', $merchantId)->count() + 1;

        $customer = Customer::create([
            'merchant_id' => $merchantId,
            'customer_code' => 'CUST-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT),
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone ?: null,
        ]);

        $this->selectCustomer($customer->id);
        $this->showNewCustomerForm = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
    }

    // --- Checkout ----------------------------------------------------------

    public function checkout(): void
    {
        if (empty($this->cart)) {
            return;
        }

        if ($this->needsDiscountApproval()) {
            $this->checkoutError = 'This discount exceeds your limit ('.$this->myDiscountLimit().'%). Ask a supervisor or manager to approve it below.';
            $this->showOverridePanel = true;

            return;
        }

        $merchantId = Auth::user()->merchant_id;
        $totals = $this->totals();
        $paymentMethod = $this->paymentMethodId
            ? PaymentMethod::where('merchant_id', $merchantId)->find($this->paymentMethodId)
            : null;
        $tendered = $this->amountTendered !== '' ? (float) $this->amountTendered : $totals['total'];

        DB::transaction(function () use ($merchantId, $totals, $paymentMethod, $tendered) {
            $sale = SalesRecord::create([
                'merchant_id' => $merchantId,
                'customer_id' => $this->customerId,
                'amount' => $totals['total'],
                'subtotal' => $totals['subtotal'],
                'discount_type' => $this->overallDiscountValue !== '' && (float) $this->overallDiscountValue > 0 ? ($this->overallDiscountType ?: 'percent') : null,
                'discount_value' => $this->overallDiscountValue !== '' ? (float) $this->overallDiscountValue : null,
                'discount_amount' => $totals['lineDiscount'] + $totals['overallDiscount'],
                'discount_approved_by' => $this->discountApprovedBy,
                'payment_method' => $paymentMethod?->name ?? 'Cash',
                'payment_method_id' => $paymentMethod?->id,
                'amount_tendered' => $tendered,
                'change_due' => max(0, $tendered - $totals['total']),
                'items_count' => $this->cartCount(),
                'description' => 'POS sale',
                'sale_date' => now()->toDateString(),
                'recorded_by' => Auth::id(),
            ]);

            foreach ($this->cart as $itemId => $line) {
                $lineGross = $line['quantity'] * $line['unit_price'];
                $lineDiscountAmount = $this->lineDiscountAmount($line);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'inventory_item_id' => $itemId,
                    'item_name' => $line['name'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'gross_amount' => $lineGross,
                    'discount_type' => $line['discount_type'] ?? null,
                    'discount_value' => $line['discount_value'] ?? null,
                    'discount_amount' => $lineDiscountAmount,
                    'subtotal' => $lineGross - $lineDiscountAmount,
                ]);

                StockMovement::create([
                    'merchant_id' => $merchantId,
                    'inventory_item_id' => $itemId,
                    'type' => StockMovement::TYPE_OUT,
                    'quantity' => $line['quantity'],
                    'reference' => 'sale',
                    'notes' => "POS sale #{$sale->id}",
                    'movement_date' => now()->toDateString(),
                    'recorded_by' => Auth::id(),
                ]);

                InventoryItem::where('id', $itemId)->decrement('quantity_on_hand', $line['quantity']);
            }
        });

        $this->lastReceiptTotal = number_format($totals['total'], 0);
        $this->cart = [];
        $this->resetDiscounts();
        $this->resetCustomer();
        $this->amountTendered = '';
    }

    public function cartCount(): float
    {
        return array_sum(array_column($this->cart, 'quantity'));
    }

    /**
     * Scan-to-add: an exact barcode/SKU match adds straight to the cart so a
     * barcode scanner (which types the code then Enter) can drive checkout.
     */
    public function updatedSearch(): void
    {
        if ($this->search === '') {
            return;
        }

        $merchantId = Auth::user()->merchant_id;

        $match = InventoryItem::where('merchant_id', $merchantId)
            ->whereNotNull('unit_price')
            ->where(function ($q) {
                $q->where('barcode', $this->search)->orWhere('sku', $this->search);
            })
            ->first();

        if ($match) {
            $this->addToCart($match->id);
            $this->search = '';
        }
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;

        $items = InventoryItem::where('merchant_id', $merchantId)
            ->whereNotNull('unit_price')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('barcode', 'like', "%{$this->search}%");
            }))
            ->with('branch')
            ->orderBy('name')
            ->get();

        $customers = $this->showCustomerPicker && $this->customerSearch !== ''
            ? Customer::where('merchant_id', $merchantId)
                ->where(function ($q) {
                    $q->where('name', 'like', "%{$this->customerSearch}%")
                        ->orWhere('phone', 'like', "%{$this->customerSearch}%");
                })
                ->limit(8)
                ->get()
            : collect();

        $paymentMethods = PaymentMethod::where('merchant_id', $merchantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('livewire.portal.pos.index', [
            'items' => $items,
            'customers' => $customers,
            'totals' => $this->totals(),
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
