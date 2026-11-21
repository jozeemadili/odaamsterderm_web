<?php

namespace App\Services;

use App\Models\HatchSchedule;
use App\Models\InventoryItem;
use App\Models\PlatformSetting;
use App\Support\CarePlans;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Quiet, sized suggestions for chicks being booked: what the chick type's
 * care plan needs in the first days, matched to what the pickup store
 * actually has on the marketplace, in whole packs for the number of chicks.
 * At most three; nothing is added unless the customer adds it.
 */
class CareSuggestions
{
    /**
     * @param  list<int>  $exceptProducts  products already in the order
     * @return list<array<string, mixed>>
     */
    public function forBooking(HatchSchedule $day, int $boxes, int $storeId, array $exceptProducts = []): array
    {
        $chicks = max(1, $boxes) * HatchSchedule::boxSize();
        $offers = $this->storeOffers($storeId)->reject(fn (InventoryItem $o) => in_array($o->product_id, $exceptProducts, true));
        $withPrice = PlatformSetting::showPrices();
        $picked = [];

        foreach (CarePlans::for($day->chickType->code) as $step) {
            if (count($picked) >= CarePlans::MAX_SUGGESTIONS) {
                break;
            }
            $choice = $this->choose($step, $offers, $chicks);
            if (! $choice) {
                continue;
            }
            [$offer, $packs, $amount] = $choice;
            $offers = $offers->reject(fn ($o) => $o->product_id === $offer->product_id);

            $picked[] = [
                'step' => $step['key'],
                'offer_id' => $offer->id,
                'product_id' => $offer->product_id,
                'name' => $offer->offerName(),
                'pack' => $offer->variant?->name,
                'quantity' => $packs,
                'unit' => $offer->sellingUnit(),
                'amount' => $amount,
                'reason' => $step['reason'],
                'reason_sw' => $step['reason_sw'],
                'image_url' => $offer->product?->imageUrl(),
                'unit_price' => $withPrice ? (float) $offer->unit_price : null,
                'line_total' => $withPrice ? round($packs * (float) $offer->unit_price, 2) : null,
            ];
        }

        return $picked;
    }

    /** Orderable offers at the store, with what matching needs loaded. */
    protected function storeOffers(int $storeId): Collection
    {
        return InventoryItem::query()
            ->where('branch_id', $storeId)
            ->onMarketplace()
            ->where('marketplace_quantity', '>', 0)
            ->with(['product.category', 'product.lifeStages', 'product.media', 'variant'])
            ->get()
            ->filter(fn (InventoryItem $o) => $o->product && $o->marketplaceBlockers() === [])
            ->values();
    }

    /**
     * The best offer for a step and how many packs: matching products, the
     * preferred names first, then the pack combination that wastes least.
     *
     * @return array{0: InventoryItem, 1: int, 2: ?string}|null
     */
    protected function choose(array $step, Collection $offers, int $chicks): ?array
    {
        $candidates = $offers->filter(fn (InventoryItem $o) => $this->matches($step, $o))->values();
        if ($candidates->isEmpty()) {
            return null;
        }

        $best = null;
        foreach ($candidates as $offer) {
            [$packs, $amount, $waste] = $this->size($step, $offer, $chicks);
            if ($packs < 1 || $packs > (float) $offer->marketplace_quantity) {
                continue; // not enough on the shelf for this order
            }
            $rank = [$this->preference($step, $offer), $waste, $packs];
            if ($best === null || $rank < $best[3]) {
                $best = [$offer, $packs, $amount, $rank];
            }
        }

        return $best ? [$best[0], $best[1], $best[2]] : null;
    }

    protected function matches(array $step, InventoryItem $offer): bool
    {
        $p = $offer->product;
        $code = (string) $p->category?->code;
        if (! collect($step['categories'])->contains(fn ($c) => str_starts_with($code, $c))) {
            return false;
        }
        $name = Str::lower($offer->offerName().' '.$p->name);

        if (isset($step['stage']) && ! $p->lifeStages->contains('code', $step['stage'])) {
            return false;
        }
        if (isset($step['disease']) && ! in_array($step['disease'], $p->disease_target ?? [], true)) {
            return false;
        }
        if (isset($step['use_cases']) && ! array_intersect($step['use_cases'], $p->use_cases ?? [])) {
            return false;
        }
        if (isset($step['must']) && ! collect($step['must'])->contains(fn ($w) => str_contains($name, $w))) {
            return false;
        }
        if (isset($step['avoid']) && collect($step['avoid'])->contains(fn ($w) => str_contains($name, $w))) {
            return false;
        }
        // Feeds for chicks only: not concentrates or premixes for mixing.
        if ($step['kind'] === 'feed' && array_intersect(['feed_mixing'], $p->use_cases ?? [])) {
            return false;
        }

        return true;
    }

    /** Lower is better: position of the first preferred word found in the name. */
    protected function preference(array $step, InventoryItem $offer): int
    {
        $name = Str::lower($offer->offerName().' '.$offer->product->name);
        foreach ($step['prefer'] ?? [] as $i => $word) {
            if (str_contains($name, $word)) {
                return $i;
            }
        }

        return 99;
    }

    /**
     * Packs needed for the chicks, a short "how much" text, and the waste
     * (what is left over in the last pack).
     *
     * @return array{0: int, 1: ?string, 2: float}
     */
    protected function size(array $step, InventoryItem $offer, int $chicks): array
    {
        $packSize = (float) ($offer->variant?->pack_size_value ?? 0);
        $unit = Str::lower((string) $offer->variant?->pack_size_unit);

        switch ($step['kind']) {
            case 'feed':
                $needKg = $chicks * $step['kg_per_chick'];
                $packKg = match ($unit) {
                    'kg' => $packSize, 'g' => $packSize / 1000, default => 0.0,
                };
                if ($packKg <= 0) {
                    return [0, null, INF];
                }
                $packs = (int) ceil($needKg / $packKg);

                return [$packs, $this->num($needKg).' kg', $packs * $packKg - $needKg];

            case 'vaccine':
                $doses = $chicks * $step['doses_per_chick'];
                if ($unit !== 'doses' || $packSize <= 0) {
                    return [0, null, INF];
                }
                $packs = (int) ceil($doses / $packSize);

                return [$packs, $doses.' doses', $packs * $packSize - $doses];

            case 'water':
                $litres = $chicks * $step['litres_per_chick_day'] * $step['days'];
                $perLitre = $this->dosePerLitre((string) $offer->product->inclusion_or_dosage);
                $packAmount = match ($unit) {
                    'g', 'ml' => $packSize, 'kg', 'l' => $packSize * 1000, default => 0.0,
                };
                if ($perLitre === null || $packAmount <= 0) {
                    // No readable dose: one of the smallest pack only.
                    return [1, null, $packAmount ?: 1000.0];
                }
                $need = $litres * $perLitre;
                $packs = max(1, (int) ceil($need / $packAmount));

                return [$packs, 'for '.$this->num($litres).' L of water', $packs * $packAmount - $need];

            case 'equipment':
                $packs = (int) ceil($chicks / $step['chicks_per_item']);

                return [$packs, null, 0.0];
        }

        return [0, null, INF];
    }

    /** "5 g per 20 L", "2 ml/litre", "1g kwa lita 1" → grams (or ml) per litre. */
    public function dosePerLitre(string $useRate): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(g|gm|grams?|ml)\b[^0-9]{0,25}?(\d+(?:[.,]\d+)?)?\s*(l|lt|litres?|liters?|lita)\b/iu', $useRate, $m)) {
            $amount = (float) str_replace(',', '.', $m[1]);
            $litres = isset($m[3]) && $m[3] !== '' ? (float) str_replace(',', '.', $m[3]) : 1.0;

            return $litres > 0 ? $amount / $litres : null;
        }

        return null;
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
