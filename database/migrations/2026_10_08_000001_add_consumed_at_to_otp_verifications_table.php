<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            // Set once an OTP has been used (e.g. to reset a password) so a
            // verified code can never be replayed.
            $table->timestampTz('consumed_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropColumn('consumed_at');
        });
    }
};
