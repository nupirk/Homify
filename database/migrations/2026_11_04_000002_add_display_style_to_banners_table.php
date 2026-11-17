<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A campaign used to be a banner or nothing. `display_style` lets the same
     * creative (and the same promo code) be presented three ways:
     *   banner - the inline carousel slide (16:9)
     *   poster - a taller inline slide (4:5) for a full image
     *   popup  - a modal shown once per session over the app
     * It is a plain string so adding a style does not need a Postgres enum
     * migration; the API validates the allowed values.
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('display_style', 20)->default('banner');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('display_style');
        });
    }
};
