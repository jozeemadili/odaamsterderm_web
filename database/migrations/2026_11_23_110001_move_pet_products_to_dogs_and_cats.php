<?php

use App\Models\AnimalType;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Pet-D Worm Tablets and Nutrovet Tablets were filed under "Other
 * livestock / animals"; they are for dogs and cats. Matched by name, so
 * only these two products change. Safe to run more than once.
 */
return new class extends Migration
{
    private const PRODUCTS = ['Pet-D Worm Tablets', 'Nutrovet Tablets'];

    public function up(): void
    {
        $dogs = AnimalType::where('code', 'AN-DOG')->first();
        $cats = AnimalType::where('code', 'AN-FEL')->first();
        if (! $dogs || ! $cats) {
            return;
        }

        foreach (Product::whereIn('name', self::PRODUCTS)->get() as $product) {
            $product->update(['animal_type_id' => $dogs->id]);
            $product->animalTypes()->sync([$dogs->id, $cats->id]);
            $product->refreshQuality();
        }
    }

    public function down(): void
    {
        $other = AnimalType::where('code', 'AN-OTH')->first();
        if (! $other) {
            return;
        }

        foreach (Product::whereIn('name', self::PRODUCTS)->get() as $product) {
            $product->update(['animal_type_id' => $other->id]);
            $product->animalTypes()->sync([$other->id]);
            $product->refreshQuality();
        }
    }
};
