<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('otp', 6);
            $table->enum('type', ['registration', 'login', 'password_reset'])->default('registration');
            $table->timestampTz('expires_at');
            $table->boolean('is_verified')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['phone', 'type', 'is_verified']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('otp_verifications');
    }
};
