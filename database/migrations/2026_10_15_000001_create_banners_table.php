<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marketing CMS banners shown across the customer app
        Schema::create('banners', function (Blueprint $table) {
            $table->id('banner_id');
            $table->string('title', 100);
            $table->string('subtitle')->nullable();
            $table->string('image_url', 255)->nullable(); // full public URL (asset('storage/banners/...'))
            $table->string('placement', 30);              // home_hero | home_mid | explore_top | cart_top
            $table->string('link_type', 20)->default('none'); // none | service | category | url | promo
            $table->string('link_value', 255)->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
