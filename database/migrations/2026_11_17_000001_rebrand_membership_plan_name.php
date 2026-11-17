<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The plan code stays `urban_plus` (it is referenced by config and stored on
     * live memberships); only the customer-facing name changes to the Homify
     * brand. Fresh installs already insert "Homify Plus" in the original
     * membership migration.
     */
    public function up(): void
    {
        DB::table('membership_plans')
            ->where('code', 'urban_plus')
            ->update([
                'name' => 'Homify Plus',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('membership_plans')
            ->where('code', 'urban_plus')
            ->update([
                'name' => 'Urban Plus',
                'updated_at' => now(),
            ]);
    }
};
