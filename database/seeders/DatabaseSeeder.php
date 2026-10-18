<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create([
            'name' => 'Odaamsterderm Hub Super Admin',
            'email' => 'admin@odaamsterderm.test',
            'user_type' => User::TYPE_INTERNAL,
        ]);
        $superAdmin->assignRole('super_admin');

        $this->call(AnimalCatalogSeeder::class);

        $this->callWith(KilimoBoraDemoSeeder::class, ['reviewerId' => $superAdmin->id]);

        $this->call(MarketplaceDemoSeeder::class);
        $this->call(ProducerDemoSeeder::class);
    }
}
