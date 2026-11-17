<?php

namespace Database\Seeders;

use App\Models\CommissionRule;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * One default commission rule per active service: 80% partner / 20% platform,
 * no city restriction, no amount slab. City or slab-specific rules can be
 * layered on later through the admin panel - the lookup prefers the most
 * specific match.
 *
 * Idempotent: keyed on (service, city, slab_start), so re-running does not
 * stack duplicate rules on top of each other.
 */
class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::where('is_active', true)->get();

        if ($services->isEmpty()) {
            $this->command->warn('No active services found - run CoreCatalogSeeder first.');

            return;
        }

        foreach ($services as $service) {
            CommissionRule::updateOrCreate(
                [
                    'service_id' => $service->service_id,
                    'city' => null,
                    'slab_start' => 0,
                ],
                [
                    'slab_end' => null,
                    'partner_pct' => 80.00,
                    'platform_pct' => 20.00,
                    'valid_from' => now()->startOfDay(),
                    'valid_to' => null,
                ]
            );
        }

        $this->command->info("Commission rules ready: {$services->count()} services at 80/20.");
    }
}
