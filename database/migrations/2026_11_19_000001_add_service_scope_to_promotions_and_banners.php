<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null = the code applies to the whole cart. Otherwise it discounts
        // only that service's line (see PromoService::eligibleBase).
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable();
            $table->index('service_id');
        });

        // null = every screen. Otherwise the banner only renders where that
        // service is in play (the cart).
        Schema::table('banners', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable();
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['service_id']);
            $table->dropColumn('service_id');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex(['service_id']);
            $table->dropColumn('service_id');
        });
    }
};
