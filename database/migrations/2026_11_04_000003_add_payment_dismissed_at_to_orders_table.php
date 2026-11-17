<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A failed/expired online attempt stays in "Awaiting Payment" for 24h so the
 * customer can retry it. There was no way to say "I am done with this one":
 * the order was already cancelled, so the cancel endpoint refused, and it kept
 * sitting in the list until it aged out. This flag lets the customer close it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_dismissed_at')->nullable()->after('payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_dismissed_at');
        });
    }
};
