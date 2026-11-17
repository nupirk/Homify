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
        // 3.3 Partner Profile & Skill
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->foreignId('partner_id')->primary()->constrained('users', 'user_id')->onDelete('cascade');
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->integer('total_ratings')->default(0);
            $table->jsonb('bank_account')->nullable(); // encrypted
            $table->timestampTz('kyc_verified_at')->nullable();
            $table->string('business_name', 100)->nullable();
            $table->string('business_address', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('bio')->nullable();
            $table->integer('experience_years')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Add status enum column
        SchemaCompat::statement("ALTER TABLE partner_profiles ADD COLUMN status partner_status_enum DEFAULT 'onboarding'");

        Schema::create('partner_skills', function (Blueprint $table) {
            $table->foreignId('partner_id')->constrained('partner_profiles', 'partner_id')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services', 'service_id')->onDelete('cascade');
            $table->decimal('base_price', 10, 2)->nullable();
            $table->primary(['partner_id', 'service_id']);
        });

        // Real-time availability (can be replaced by Redis or Timescale)
        Schema::create('partner_availability', function (Blueprint $table) {
            $table->id('availability_id');
            $table->foreignId('partner_id')->constrained('partner_profiles', 'partner_id')->onDelete('cascade');
            $table->date('service_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_length_min')->default(60);
            $table->boolean('booked')->default(false);
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_availability');
        Schema::dropIfExists('partner_skills');
        Schema::dropIfExists('partner_profiles');
    }
};
