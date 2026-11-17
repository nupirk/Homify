<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Notification Templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id('template_id');
            $table->string('name', 100)->unique();
            $table->enum('type', [
                'booking_confirmed',
                'booking_updated',
                'booking_cancelled',
                'partner_assigned',
                'partner_on_way',
                'service_started',
                'service_completed',
                'payment_due',
                'payment_received',
                'review_request',
                'promotional',
                'system_update',
                'reminder',
                'custom'
            ]);
            $table->string('title_template', 200);
            $table->text('message_template');
            $table->json('variables')->nullable(); // Required template variables
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users', 'user_id')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users', 'user_id')->onDelete('set null');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Notification Campaigns
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id('campaign_id');
            $table->string('name', 100);
            $table->foreignId('template_id')->constrained('notification_templates', 'template_id')->onDelete('cascade');
            $table->enum('target_type', [
                'all_users',
                'all_customers',
                'all_partners',
                'specific_users',
                'partners_with_negative_balance',
                'inactive_users',
                'high_value_customers',
                'new_users',
                'custom_criteria'
            ]);
            $table->json('target_criteria')->nullable(); // Specific targeting rules
            $table->enum('status', ['draft', 'pending', 'scheduled', 'sending', 'completed', 'failed', 'cancelled'])->default('draft');
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users', 'user_id')->onDelete('cascade');
            $table->integer('total_recipients')->default(0);
            $table->integer('successful_sends')->default(0);
            $table->integer('failed_sends')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Notification Logs
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->foreignId('campaign_id')->nullable()->constrained('notification_campaigns', 'campaign_id')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('device_id')->nullable()->constrained('user_devices', 'device_id')->onDelete('set null');
            $table->foreignId('notification_id')->nullable()->constrained('notifications', 'notification_id')->onDelete('set null');
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'expired'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->string('platform', 20)->nullable(); // ios, android, web
            $table->string('device_token', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'status']);
            $table->index(['campaign_id', 'status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_campaigns');
        Schema::dropIfExists('notification_templates');
    }
};
