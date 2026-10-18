<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * What a live server needs: roles/permissions and the starter animal
 * catalog. No demo sellers, customers or test passwords — create the
 * first administrator with `php artisan app:create-admin`.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AnimalCatalogSeeder::class,
        ]);
    }
}
