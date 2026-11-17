<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        SchemaCompat::statement("ALTER TABLE partner_profiles ADD COLUMN IF NOT EXISTS live_lat DOUBLE PRECISION NULL");
        SchemaCompat::statement("ALTER TABLE partner_profiles ADD COLUMN IF NOT EXISTS live_lng DOUBLE PRECISION NULL");
        SchemaCompat::statement("ALTER TABLE partner_profiles ADD COLUMN IF NOT EXISTS live_updated_at TIMESTAMP NULL");
    }

    public function down(): void
    {
        SchemaCompat::statement("ALTER TABLE partner_profiles DROP COLUMN IF EXISTS live_lat");
        SchemaCompat::statement("ALTER TABLE partner_profiles DROP COLUMN IF EXISTS live_lng");
        SchemaCompat::statement("ALTER TABLE partner_profiles DROP COLUMN IF EXISTS live_updated_at");
    }
};
