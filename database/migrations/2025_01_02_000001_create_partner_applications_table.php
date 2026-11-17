<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->id('application_id');

            // Personal Information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('emergency_contact')->nullable();

            // Address Information
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');

            // Professional Information
            $table->integer('experience_years')->default(0);
            $table->json('previous_work')->nullable(); // Array of previous work experience
            $table->text('why_partner'); // Why they want to be a partner
            $table->text('bio')->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->json('availability_hours')->nullable(); // Available hours/days
            $table->json('portfolio_links')->nullable(); // Portfolio/work samples
            $table->json('references')->nullable(); // Professional references

            // Application Status
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'converted'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Documents/Files
            $table->json('documents')->nullable(); // Array of uploaded documents

            // Created partner reference (if converted)
            $table->foreignId('created_partner_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['email', 'phone']);
        });

        // Pivot table for partner application services
        Schema::create('partner_application_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('partner_applications', 'application_id')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services', 'service_id')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['application_id', 'service_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_application_services');
        Schema::dropIfExists('partner_applications');
    }
};
