<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. USERS & AUTHENTICATION
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email', 100)->unique();
            $table->string('phone', 20)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar_url')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            // Partner application tracking (admin review flow)
            $table->enum('partner_application_status', [
                'none',
                'pending',
                'approved',
                'rejected'
            ])->default('none');
            $table->timestamp('partner_application_submitted_at')->nullable();
            $table->timestamp('partner_application_reviewed_at')->nullable();
            $table->text('partner_application_rejection_reason')->nullable();
            $table->unsignedBigInteger('partner_application_reviewed_by')->nullable();
            $table->foreign('partner_application_reviewed_by')->references('user_id')->on('users')->onDelete('set null');
        });

        // Add user_type enum column
        DB::statement("ALTER TABLE users ADD COLUMN user_type user_type_enum DEFAULT 'customer'");

        // 2. ADDRESSES
        Schema::create('addresses', function (Blueprint $table) {
            $table->id('address_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 50);
            $table->string('state', 50);
            $table->string('postal_code', 20);
            $table->string('country', 50)->default('USA');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        // Add address_type enum column
        DB::statement("ALTER TABLE addresses ADD COLUMN address_type address_type_enum DEFAULT 'home'");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
