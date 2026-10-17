<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'business_name', 'owner_name', 'phone', 'email', 'business_type', 'business_type_id',
    'tin_number', 'physical_address', 'region', 'region_id', 'city',
    'kyc_status', 'subscription_tier', 'status', 'brand_color',
    'created_by_agent_id', 'reviewed_by', 'reviewed_at', 'review_notes',
])]
class Merchant extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

    public const DEFAULT_BRAND_COLOR = '#01162F';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function logo(): ?Media
    {
        return $this->getFirstMedia('logo');
    }

    public function brandColor(): string
    {
        return $this->brand_color ?: self::DEFAULT_BRAND_COLOR;
    }

    public function logoDataUri(): ?string
    {
        $logo = $this->logo();

        if (! $logo) {
            return null;
        }

        $path = $logo->getPath();

        if (! is_file($path)) {
            return null;
        }

        return 'data:'.$logo->mime_type.';base64,'.base64_encode(file_get_contents($path));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['business_name', 'kyc_status', 'status', 'subscription_tier', 'reviewed_by'])
            ->logOnlyDirty()
            ->useLogName('merchant');
    }

    public const KYC_PENDING = 'pending';

    public const KYC_UNDER_REVIEW = 'under_review';

    public const KYC_APPROVED = 'approved';

    public const KYC_REJECTED = 'rejected';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function salesRecords(): HasMany
    {
        return $this->hasMany(SalesRecord::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function lowStockItems(): HasMany
    {
        return $this->inventoryItems()->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }

    public function expiringSoonItems(): HasMany
    {
        return $this->inventoryItems()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addMonth())
            ->whereDate('expiry_date', '>=', now());
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    public function saleItems(): HasManyThrough
    {
        return $this->hasManyThrough(SaleItem::class, SalesRecord::class, 'merchant_id', 'sale_id');
    }

    public function stockReceipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function discountLimits(): HasMany
    {
        return $this->hasMany(DiscountLimit::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function inventoryCategories(): HasMany
    {
        return $this->hasMany(InventoryCategory::class);
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }





    public function createdByAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_agent_id');
    }

    /**
     * Named *Ref (not businessType/region) so it doesn't collide with the
     * legacy free-text `business_type`/`region` string columns kept for
     * backward compatibility on old records.
     */
    public function businessTypeRef(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    public function regionRef(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
