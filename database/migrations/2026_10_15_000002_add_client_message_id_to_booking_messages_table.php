<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_messages', function (Blueprint $table) {
            // Client-generated idempotency key. A send that is retried (flaky
            // network) or double-tapped carries the SAME key, and the unique
            // index lets the controller return the already-stored row instead
            // of inserting a duplicate. Nullable so older clients still work;
            // Postgres allows many NULLs in a unique index.
            $table->string('client_message_id', 64)->nullable()->after('sender_id');

            $table->unique(
                ['order_id', 'sender_id', 'client_message_id'],
                'booking_messages_client_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table) {
            $table->dropUnique('booking_messages_client_key_unique');
            $table->dropColumn('client_message_id');
        });
    }
};
