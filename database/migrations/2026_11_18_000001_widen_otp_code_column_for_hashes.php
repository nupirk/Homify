<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Codes are stored as sha256 HMACs (64 hex chars), never plaintext, so
        // a leaked database alone cannot be used to reset accounts.
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->string('otp', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->string('otp', 6)->change();
        });
    }
};
