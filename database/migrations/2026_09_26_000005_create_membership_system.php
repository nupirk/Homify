<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('duration_days')->default(30);
            // perk bag, e.g. {"discount_percent": 10, "max_discount": 500}
            $table->json('perks');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('membership_plans')->onDelete('cascade');
            // pending = awaiting gateway payment; active/expired = entitlement
            // windows; cancelled/failed = terminal non-entitlements.
            $table->string('status', 20)->default('pending');
            $table->decimal('price_paid', 10, 2)->default(0);
            $table->string('gateway', 20)->nullable();
            // eSewa transaction_uuid - the single lookup key for callbacks.
            $table->string('txn_id', 64)->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // Membership share of discount_amount (promo stays in
            // discount_amount too - tax/gateway/settlement read the total).
            $table->decimal('membership_discount', 10, 2)
                ->default(0)
                ->after('discount_amount');
        });

        // Launch plan. updateOrInsert uses the serial default for id, so the
        // sequence advances correctly and later model creates never collide.
        DB::table('membership_plans')->updateOrInsert(
            ['code' => 'urban_plus'],
            [
                'name' => 'Homify Plus',
                'description' => '10% off every booking, up to Rs 500 saved per order.',
                'price' => 299.00,
                'duration_days' => 30,
                'perks' => json_encode(['discount_percent' => 10, 'max_discount' => 500]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('membership_discount');
        });

        Schema::dropIfExists('user_memberships');
        Schema::dropIfExists('membership_plans');
    }
};
