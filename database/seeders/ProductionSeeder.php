<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a brand-new production database needs, and nothing else:
 * no demo customers, partners, addresses or orders.
 *
 *   php artisan db:seed              # runs this via DatabaseSeeder
 *   php artisan db:seed --class=ProductionSeeder --force
 *
 * Re-runnable: every seeder is idempotent.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,        // first, so the admin becomes user_id 1
            CoreCatalogSeeder::class,  // categories, services, standard prices
            CommissionRuleSeeder::class, // default 80/20 split per service
        ]);
    }
}
