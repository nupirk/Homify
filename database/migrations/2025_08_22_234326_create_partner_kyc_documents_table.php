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
        Schema::create('partner_kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->enum('document_type', [
                'license',
                'insurance',
                'identity',
                'address_proof',
                'bank_statement',
                'tax_certificate'
            ]);
            $table->string('document_name');
            $table->string('file_path'); // Encrypted storage path
            $table->bigInteger('file_size'); // Size in bytes
            $table->string('file_type', 10); // pdf, jpg, png, etc.
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->timestamp('uploaded_at');
            $table->timestamp('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable(); // Admin user ID
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('partner_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('user_id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['partner_id', 'document_type']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_kyc_documents');
    }
};
