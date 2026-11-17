<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        SchemaCompat::statement("DO $$ BEGIN ALTER TYPE order_status_enum ADD VALUE IF NOT EXISTS 'pending_reassignment'; EXCEPTION WHEN duplicate_object THEN null; END $$;");
    }

    public function down()
    {
        // PostgreSQL cannot remove enum values; the value is harmless if kept.
    }
};