<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up()
    {
        // 5. PAYMENTS & FINANCIAL

        // Modern payment gateway tables for Nepal e-wallets
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->char('booking_id', 36); // UUID to match orders.order_id
            $table->enum('gateway', ['khalti', 'esewa', 'stripe']);
            $table->string('txn_id', 64)->nullable(); // Gateway transaction ID
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['initiated', 'paid', 'failed', 'refunded'])->default('initiated');
            $table->json('metadata')->nullable(); // Raw gateway payload
            $table->timestamp('expires_at')->nullable(); // 15-minute TTL
            $table->timestamps();

            $table->foreign('booking_id')->references('order_id')->on('orders')->onDelete('cascade');

            $table->index(['booking_id', 'status']);
            $table->index(['gateway', 'txn_id']);
            $table->index('expires_at');
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('gateway_order_id', 64)->nullable();
            $table->enum('attempt_status', ['initiated', 'redirected', 'completed', 'failed', 'timeout']);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'attempt_status']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id('payment_method_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('provider', 50); // stripe, paypal, razorpay, etc.
            $table->string('method_type', 50); // card, bank_account, digital_wallet
            $table->string('method_display_name', 100); // user-friendly display name
            $table->text('encrypted_details'); // encrypted payment method details (text, not jsonb)
            $table->boolean('is_default')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->char('transaction_id', 36)->primary(); // UUID
            $table->char('order_id', 36)->nullable();
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods', 'payment_method_id')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('NPR');
            $table->string('provider_transaction_id')->nullable(); // Stripe, PayPal etc.
            $table->jsonb('provider_response')->nullable(); // Raw provider response
            $table->text('failure_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('cascade');
        });

        // Add type and status enum columns
        SchemaCompat::statement("ALTER TABLE transactions ADD COLUMN transaction_type transaction_type_enum DEFAULT 'payment'");
        SchemaCompat::statement("ALTER TABLE transactions ADD COLUMN status transaction_status_enum DEFAULT 'pending'");

        // LEDGER: Double-Entry Bookkeeping
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id('account_id');
            $table->string('account_code', 20)->unique();
            $table->string('account_name', 100);
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Add account_type enum column
        SchemaCompat::statement("ALTER TABLE ledger_accounts ADD COLUMN account_type account_type_enum");

        // Skip the old complex ledger structure - we use simple ledger below

        // Partner Payouts
        Schema::create('partner_payouts', function (Blueprint $table) {
            $table->id('payout_id');
            $table->foreignId('partner_id')->constrained('partner_profiles', 'partner_id')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('payment_method')->default('bank_transfer'); // khalti, esewa, bank_transfer
            $table->string('transaction_reference')->nullable(); // Gateway transaction ID
            $table->decimal('gateway_fee', 10, 2)->default(0);
            $table->jsonb('bank_transfer_details')->nullable();
            $table->jsonb('metadata')->nullable(); // Additional data like user_agent, IP, etc.
            $table->text('notes')->nullable(); // Admin notes
            $table->foreignId('processed_by')->nullable()->constrained('users', 'user_id')->onDelete('set null');
            $table->timestampTz('initiated_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Add status enum column
        SchemaCompat::statement("ALTER TABLE partner_payouts ADD COLUMN status payout_status_enum DEFAULT 'pending'");

        // EARNINGS SYSTEM
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id('rule_id');
            $table->foreignId('service_id')->constrained('services', 'service_id')->onDelete('cascade');
            $table->string('city')->nullable(); // NULL = default
            $table->decimal('slab_start', 10, 2)->default(0); // 0 means "from first rupee"
            $table->decimal('slab_end', 10, 2)->nullable(); // NULL = no upper bound
            $table->decimal('partner_pct', 5, 2); // e.g. 75.00 = 75%
            $table->decimal('platform_pct', 5, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->nullable();

            $table->index(['service_id', 'city', 'valid_from', 'valid_to']);
        });

        // Simple double-entry ledger table
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('booking_id')->nullable(); // nullable for non-order entries
            $table->foreignId('user_id')->constrained('users', 'user_id'); // who owns the money
            // Later migrations extend this list (COD and PAS settlements). PostgreSQL
            // does that by rebuilding the enum; SQLite cannot alter a CHECK, so there
            // the column is a string the application validates.
            $ledgerTypes = [
                'CUSTOMER_DEBIT',        // money locked from customer
                'ESCROW_CREDIT',         // money parked in platform
                'PARTNER_EARNING',       // paid out to partner
                'PLATFORM_FEE',          // platform revenue
                'TAX_PAYABLE',           // GST owed to govt
                'WITHDRAWAL',            // partner cashes out
                'REFUND',                // full or partial refund
                'COD_RECEIPT',           // cash collected on delivery
                'COD_COMMISSION',        // platform commission on COD
                'WITHDRAWAL_COMPLETED',  // withdrawal processed by admin
                'BALANCE_RECONCILIATION',// manual balance fix
                'WITHDRAWAL_REVERSAL'    // withdrawal reverted
            ];
            SchemaCompat::isPostgres()
                ? $table->enum('type', $ledgerTypes)
                : $table->string('type', 40);
            $table->decimal('amount', 12, 2)->check('amount >= 0');
            $table->char('currency', 3)->default('NPR');
            $table->decimal('balance_after', 12, 2)->nullable(); // running balance of user's wallet
            $table->text('narration')->nullable();
            $table->timestampTz('created_at', 6)->nullable(false);
            // Foreign key constraint for booking_id
            $table->foreign('booking_id')->references('order_id')->on('orders')->onDelete('cascade');

            // Unique constraint for idempotency (prevent duplicate entries)
            $table->unique(['booking_id', 'user_id', 'type'], 'ledger_booking_user_type_unique');

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['booking_id']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('partner_balances', function (Blueprint $table) {
            $table->id('balance_id');
            $table->foreignId('partner_id')->constrained('partner_profiles', 'partner_id')->onDelete('cascade');
            $table->decimal('available_balance', 12, 2)->default(0); // Money ready for payout (PREPAID)
            $table->decimal('total_earnings', 12, 2)->default(0);     // Total lifetime earnings
            $table->decimal('total_withdrawn', 12, 2)->default(0);    // Total amount withdrawn
            $table->timestampTz('last_updated');
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->nullable();

            $table->unique('partner_id');
        });
    }

    public function down()
    {
        // Drop tables in reverse dependency order
        Schema::dropIfExists('partner_payouts');
        Schema::dropIfExists('partner_balances');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
