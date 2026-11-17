<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        SchemaCompat::statement("
            CREATE TABLE error_logs (
                " . SchemaCompat::serialPrimaryKey() . ",
                fingerprint VARCHAR(64) NOT NULL,
                level VARCHAR(20) NOT NULL DEFAULT 'error',
                message TEXT NOT NULL,
                exception_class VARCHAR(191) NULL,
                file VARCHAR(255) NULL,
                line INTEGER NULL,
                stack TEXT NULL,
                context JSONB NULL,
                screen VARCHAR(120) NULL,
                platform VARCHAR(32) NULL,
                app_version VARCHAR(32) NULL,
                user_id BIGINT NULL REFERENCES users(user_id) ON DELETE SET NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                occurrences INTEGER NOT NULL DEFAULT 1,
                first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        SchemaCompat::statement("CREATE INDEX error_logs_fingerprint_idx ON error_logs (fingerprint)");
        SchemaCompat::statement("CREATE INDEX error_logs_last_seen_idx ON error_logs (last_seen_at)");
        SchemaCompat::statement("CREATE INDEX error_logs_level_idx ON error_logs (level, last_seen_at)");
    }

    public function down(): void
    {
        SchemaCompat::statement("DROP TABLE IF EXISTS error_logs");
    }
};
