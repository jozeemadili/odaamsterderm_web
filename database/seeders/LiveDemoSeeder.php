<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * The development demo marketplace for a live server: the same sellers,
 * stores (with map locations and hours), stock, marketplace listings and
 * producers — but no demo logins, because those all share a known
 * password. Give a demo seller a login with:
 *   php artisan app:create-account seller you@example.com --existing-business="Tegeta Feeds & Vet"
 *
 * Run once: php artisan db:seed --class=LiveDemoSeeder --force
 */
class LiveDemoSeeder extends Seeder
{
    public const BUSINESSES = ['Kilimo Bora Agrovet', 'Tegeta Feeds & Vet', 'Mbezi Livestock Supplies'];

    public function run(): void
    {
        if (Merchant::whereIn('business_name', self::BUSINESSES)->exists()) {
            $this->command?->warn('Demo sellers are already loaded — nothing changed.');

            return;
        }

        $this->call([RolesAndPermissionsSeeder::class, AnimalCatalogSeeder::class]);
        $this->callWith(KilimoBoraDemoSeeder::class, ['withLogins' => false]);
        $this->callWith(MarketplaceDemoSeeder::class, ['withLogins' => false]);
        $this->call(ProducerDemoSeeder::class);

        Merchant::whereIn('business_name', self::BUSINESSES)->pluck('id')
            ->each(fn (int $id) => PaymentMethod::seedDefaultsFor($id));
    }
}
