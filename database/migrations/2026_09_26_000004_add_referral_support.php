<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Each customer gets a shareable invite code (lazy-generated).
            $table->string('referral_code', 12)->nullable()->unique();
            // Who invited this customer; set at most once, ever.
            $table->foreignId('referred_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id('reward_id');
            $table->foreignId('referrer_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('referee_id')->constrained('users', 'user_id')->onDelete('cascade');
            // Promo issued to the referee the moment they redeem an invite code.
            $table->string('welcome_promo_code', 20)->nullable();
            // Promo issued to the referrer once the referee completes a booking.
            $table->string('reward_promo_code', 20)->nullable();
            // pending = waiting on referee's first completion; credited = done.
            $table->string('status', 20)->default('pending');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('credited_at')->nullable();

            // One attribution per referee - a code can be redeemed only once.
            $table->unique('referee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
