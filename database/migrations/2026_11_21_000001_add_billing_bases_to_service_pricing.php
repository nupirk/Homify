<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

/**
 * service_pricing.unit only knew physical units (hour, sqft, room, piece, kg,
 * liter, fixed). Admins need to say what a tier's price covers: a duration, a
 * piece, a count ("nos"), a generic unit, or the complete job.
 *
 * SQLite has no enum types - the column is a plain string there, and the
 * application validates the allowed values - so SchemaCompat skips this DDL.
 */
return new class extends Migration
{
    private const NEW_VALUES = ['duration', 'nos', 'unit', 'complete'];

    public function up(): void
    {
        foreach (self::NEW_VALUES as $value) {
            SchemaCompat::statement(
                "ALTER TYPE unit_enum ADD VALUE IF NOT EXISTS '{$value}'"
            );
        }

        // New tiers default to duration billing; existing rows keep their unit.
        if (SchemaCompat::isPostgres()) {
            DB::statement("ALTER TABLE service_pricing ALTER COLUMN unit SET DEFAULT 'duration'");
        }
    }

    public function down(): void
    {
        // PostgreSQL cannot remove enum values in place. Dropping them would
        // require rewriting the type and every dependent row, so the values
        // stay; the application stops offering them if this code is reverted.
    }
};
