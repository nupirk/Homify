<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 9. NOTIFICATIONS & TRACKING (Event Sourcing)
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('title', 100);
            $table->text('message');
            $table->jsonb('data')->nullable(); // Additional payload
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // Add notification_type enum column
        SchemaCompat::statement("ALTER TABLE notifications ADD COLUMN notification_type notification_type_enum DEFAULT 'info'");

        // Event Store for Event Sourcing
        Schema::create('event_store', function (Blueprint $table) {
            $table->id('event_id');
            $table->string('aggregate_id', 36); // UUID of the entity (order, user, etc.)
            $table->string('aggregate_type', 50); // order, user, service_request, etc.
            $table->string('event_type', 100); // order_created, payment_completed, etc.
            $table->jsonb('event_data'); // Full event payload
            $table->integer('version'); // For optimistic concurrency
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['aggregate_id', 'version']);
        });

        // Audit Log (who did what when)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->onDelete('set null');
            $table->string('action', 100); // create, update, delete, view
            $table->string('entity_type', 50); // user, order, service, etc.
            $table->string('entity_id', 36); // ID of the affected entity
            $table->jsonb('old_values')->nullable(); // Before state
            $table->jsonb('new_values')->nullable(); // After state
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // 10. CONFIGURATION & METADATA
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('setting_key', 100)->primary();
            $table->text('setting_value');
            $table->string('data_type', 20)->default('string'); // string, integer, boolean, json
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Feature Flags (for A/B testing, gradual rollouts)
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id('flag_id');
            $table->string('flag_key', 100)->unique();
            $table->string('flag_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->decimal('rollout_percentage', 5, 2)->default(0); // 0-100%
            $table->jsonb('conditions')->nullable(); // User segments, etc.
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // User sessions/devices (for push notifications, etc.)
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id('device_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('device_token', 255); // FCM, APN token
            $table->string('platform', 20); // ios, android, web
            $table->string('device_model', 100)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->unique(['user_id', 'device_token']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('event_store');
        Schema::dropIfExists('notifications');
    }
};
