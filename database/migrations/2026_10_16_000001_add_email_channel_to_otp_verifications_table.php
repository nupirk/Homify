<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            // Phone remains the default channel, but email verification only
            // needs an address - so a row may carry either identifier.
            $table->string('phone', 20)->nullable()->change();
            $table->string('email', 255)->nullable()->after('phone');
            $table->string('channel', 10)->default('phone')->after('email');

            $table->index(['email', 'type', 'is_verified']);
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropIndex(['email', 'type', 'is_verified']);
            $table->dropColumn(['email', 'channel']);
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};
