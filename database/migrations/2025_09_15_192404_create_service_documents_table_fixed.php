<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_completion_documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('uploaded_by');

            // Document information
            $table->enum('document_type', ['signed_form', 'bill_receipt', 'before_photo', 'after_photo', 'other'])
                ->comment('Type of completion document');
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255)->unique();
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->string('file_hash', 64)->comment('SHA-256 hash for integrity verification');

            // Security and access control
            $table->string('access_token', 64)->unique()->comment('Secure token for file access');
            $table->timestamp('expires_at')->nullable()->comment('Document access expiration');
            $table->boolean('is_verified')->default(false)->comment('Admin verified document');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Soft delete support
            $table->boolean('is_deleted')->default(false);
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();

            // Access tracking
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);

            // Additional metadata
            $table->json('metadata')->nullable()->comment('Additional document metadata');

            $table->timestamps();

            // Define foreign keys manually with correct column references
            $table->foreign('service_request_id')->references('request_id')->on('service_requests')->onDelete('cascade');
            $table->foreign('partner_id')->references('partner_id')->on('partner_profiles')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('user_id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index(['service_request_id', 'document_type']);
            $table->index(['partner_id', 'created_at']);
            $table->index(['is_deleted', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_completion_documents');
    }
};
