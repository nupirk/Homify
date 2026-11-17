<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * service_requests.status predates 'enroute' as a service-request state: the
 * enum only carries pending/assigned/accepted/rejected/in_progress/working/
 * completed/cancelled. Application code already writes 'enroute' (a partner
 * who starts travelling to the job), so every such write raised Postgres
 * 22P02 "invalid input value for enum service_request_status_enum" and the
 * request failed with a 500 - including cancelling a booking whose partner
 * had begun travelling.
 *
 * Postgres cannot remove a single enum value, so down() is intentionally a
 * no-op: the value is additive and harmless to older code.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TYPE service_request_status_enum ADD VALUE IF NOT EXISTS 'enroute'");
    }

    public function down(): void
    {
        // No-op: Postgres has no DROP VALUE for enums.
    }
};
