<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_messages', function (Blueprint $table) {
            $table->bigIncrements('message_id');
            $table->char('order_id', 36);
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('cascade');
            $table->foreign('sender_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['order_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_messages');
    }
};
