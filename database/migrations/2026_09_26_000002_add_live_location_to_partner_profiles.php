<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE partner_profiles ADD COLUMN IF NOT EXISTS live_lat DOUBLE PRECISION NULL");
        DB::statement("ALTER TABLE partner_profiles ADD COLUMN IF NOT EXISTS live_lng DOUBLE PRECISION NULL");
        DB::statement("ALTER TABLE partner_profiles ADD COLUMN IF NOT EXISTS live_updated_at TIMESTAMP NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE partner_profiles DROP COLUMN IF EXISTS live_lat");
        DB::statement("ALTER TABLE partner_profiles DROP COLUMN IF EXISTS live_lng");
        DB::statement("ALTER TABLE partner_profiles DROP COLUMN IF EXISTS live_updated_at");
    }
};
