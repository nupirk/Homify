<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Default `php artisan db:seed` target: production-safe reference data.
     * Demo customers, partners and orders are intentionally not seeded here.
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);
    }
}
