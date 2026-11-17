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
        // 3.1 Service Catalog
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->foreignId('parent_id')->nullable()->constrained('service_categories', 'category_id')->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id('service_id');
            $table->foreignId('category_id')->constrained('service_categories', 'category_id')->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->jsonb('features')->nullable(); // Array of features
            $table->text('terms_conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // 3.2 Dynamic Pricing
        Schema::create('service_pricing', function (Blueprint $table) {
            $table->id('pricing_id');
            $table->foreignId('service_id')->constrained('services', 'service_id')->onDelete('cascade');
            $table->string('tier_name', 50); // basic, standard, premium
            $table->decimal('base_price', 10, 2);
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->integer('estimated_duration_min')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Add unit enum column
        SchemaCompat::statement("ALTER TABLE service_pricing ADD COLUMN unit unit_enum DEFAULT 'hour'");
    }

    public function down()
    {
        Schema::dropIfExists('service_pricing');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
