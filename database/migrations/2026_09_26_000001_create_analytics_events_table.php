<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE analytics_events (
                id BIGSERIAL PRIMARY KEY,
                event VARCHAR(64) NOT NULL,
                user_id BIGINT NULL REFERENCES users(user_id) ON DELETE SET NULL,
                session_id VARCHAR(64) NULL,
                platform VARCHAR(32) NULL,
                app_version VARCHAR(32) NULL,
                props JSONB NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("CREATE INDEX analytics_events_event_created_idx ON analytics_events (event, created_at)");
        DB::statement("CREATE INDEX analytics_events_user_id_idx ON analytics_events (user_id)");
        DB::statement("CREATE INDEX analytics_events_created_at_idx ON analytics_events (created_at)");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS analytics_events");
    }
};
