<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An agent's request for a product missing from the catalogue, with photos
 * of the front and back label. Agents never create catalogue products; the
 * data team builds one from the label (or links an existing one).
 */
#[Fillable([
    'merchant_id', 'requested_by', 'name', 'producer_id', 'producer_name', 'pack_size', 'notes', 'inventory_item_id',
    'status', 'product_id', 'product_variant_id', 'ai_draft', 'reviewed_by', 'reviewed_at', 'review_notes',
])]
class ProductRequest extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    public const PENDING = 'pending';

    /** A new catalogue product was made from the label. */
    public const APPROVED = 'approved';

    /** It was already in the catalogue; linked to that product. */
    public const LINKED = 'linked';

    public const REJECTED = 'rejected';

    public const LABELS = [
        self::PENDING => 'Waiting for review',
        self::APPROVED => 'Added to the catalogue',
        self::LINKED => 'Matched to a catalogue product',
        self::REJECTED => 'Not added',
    ];

    public const FRONT = 'label_front';

    public const BACK = 'label_back';

    protected function casts(): array
    {
        return ['ai_draft' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'product_id', 'reviewed_by', 'review_notes'])->logOnlyDirty()->useLogName('catalog');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FRONT)->singleFile();
        $this->addMediaCollection(self::BACK)->singleFile();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function producerLabel(): ?string
    {
        return $this->producer?->name ?? $this->producer_name;
    }

    public function photoUrl(string $side): ?string
    {
        return $this->getFirstMediaUrl($side) ?: null;
    }

    /** Path of a label photo on disk, for the AI to read. */
    public function photoPath(string $side): ?string
    {
        return $this->getFirstMedia($side)?->getPath();
    }
}
