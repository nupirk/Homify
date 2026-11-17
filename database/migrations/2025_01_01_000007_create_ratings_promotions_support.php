<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 6. RATINGS & REVIEWS
        Schema::create('ratings', function (Blueprint $table) {
            $table->id('rating_id');
            $table->char('order_id', 36);
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained('partner_profiles', 'partner_id')->onDelete('cascade');
            $table->integer('rating_value'); // 1-5
            $table->text('review_text')->nullable();
            $table->text('partner_response')->nullable();
            $table->timestampTz('partner_responded_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('cascade');
            $table->unique(['order_id', 'user_id', 'partner_id']); // One rating per order
        });

        // 7. PROMOTIONS & COUPONS
        Schema::create('promotions', function (Blueprint $table) {
            $table->id('promotion_id');
            $table->string('promo_code', 20)->unique();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('min_order_value', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Add promo_type enum column
        DB::statement("ALTER TABLE promotions ADD COLUMN promo_type promo_type_enum DEFAULT 'percentage'");

        // User-specific promotions (for targeted marketing)
        Schema::create('user_promotions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('promotion_id')->constrained('promotions', 'promotion_id')->onDelete('cascade');
            $table->boolean('used')->default(false);
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['user_id', 'promotion_id']);
        });

        // 8. SUPPORT & TICKETING
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id('ticket_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->char('order_id', 36)->nullable();
            $table->string('subject', 200);
            $table->text('description');
            $table->integer('priority')->default(2); // 1=low, 2=medium, 3=high, 4=urgent
            $table->foreignId('assigned_to')->nullable()->constrained('users', 'user_id')->onDelete('set null');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('set null');
        });

        // Add status enum column
        DB::statement("ALTER TABLE support_tickets ADD COLUMN status support_ticket_status_enum DEFAULT 'open'");

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id('message_id');
            $table->foreignId('ticket_id')->constrained('support_tickets', 'ticket_id')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->text('message_text');
            $table->jsonb('attachments')->nullable(); // Array of file URLs
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('user_promotions');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('ratings');
    }
};
