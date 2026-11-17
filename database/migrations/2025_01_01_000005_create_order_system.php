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
        // 4. ORDER & BOOKING
        Schema::create('orders', function (Blueprint $table) {
            $table->char('order_id', 36)->primary(); // UUID
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->char('promo_code', 20)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('service_charge', 8, 2)->default(0);
            $table->decimal('platform_fee', 8, 2)->default(0);
            $table->decimal('coupon_discount', 8, 2)->default(0);
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('tip_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('final_payable', 10, 2)->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            // A later migration adds 'pas' to this column. PostgreSQL gets it by
            // altering the type; SQLite cannot drop the generated CHECK, so there
            // it stays a plain string the application validates.
            SchemaCompat::isPostgres()
                ? $table->enum('payment_method', ['online', 'cod'])->default('online')
                    ->comment('Payment method: online (prepaid) or cod (cash on delivery)')
                : $table->string('payment_method', 20)->default('online')
                    ->comment('Payment method: online (prepaid) or cod (cash on delivery)');
            $table->boolean('commission_deducted')->default(false)
                ->comment('Whether commission has been deducted from partner for COD orders');
            $table->timestamp('commission_deducted_at')->nullable()
                ->comment('When commission was deducted for COD orders');
            $table->string('transaction_uuid')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Add status enum column
        SchemaCompat::statement("ALTER TABLE orders ADD COLUMN status order_status_enum DEFAULT 'created'");

        Schema::create('order_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->char('order_id', 36);
            $table->foreignId('service_id')->constrained('services', 'service_id')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->jsonb('service_details')->nullable(); // snapshot of service details

            $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('cascade');
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id('request_id');
            $table->char('order_id', 36);
            $table->unsignedBigInteger('order_item_id')->nullable()
                ->comment('Links service request to a specific order item');
            $table->foreignId('partner_id')->nullable()->constrained('partner_profiles', 'partner_id')->onDelete('set null');
            $table->foreignId('user_address_id')->nullable()->constrained('addresses', 'address_id')->onDelete('cascade');
            $table->date('service_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->text('special_instructions')->nullable();

            // Partner assignment tracking (accept/reject/timeout/reassign)
            $table->timestampTz('partner_assigned_at')->nullable();
            $table->timestamp('assignment_expires_at')->nullable();
            $table->integer('assignment_timeout_minutes')->default(30);
            $table->timestamp('partner_accepted_at')->nullable();
            $table->timestamp('partner_rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('response_notes')->nullable();
            $table->integer('reassignment_count')->default(0);
            $table->json('reassignment_history')->nullable();

            // Service completion tracking
            $table->timestamp('work_started_at')->nullable();
            $table->timestamp('work_completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->boolean('documents_uploaded')->default(false);
            $table->boolean('customer_confirmed')->default(false);
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->integer('customer_rating')->nullable();
            $table->text('customer_feedback')->nullable();

            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('cascade');
            $table->foreign('order_item_id')->references('item_id')->on('order_items')->onDelete('cascade');
            $table->index('order_item_id');
        });

        // Add status enum column
        SchemaCompat::statement("ALTER TABLE service_requests ADD COLUMN status service_request_status_enum DEFAULT 'pending'");
    }

    public function down()
    {
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
