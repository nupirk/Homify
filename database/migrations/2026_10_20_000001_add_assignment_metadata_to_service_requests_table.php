<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('service_requests', 'assignment_method')) {
                $table->string('assignment_method', 32)->nullable()->after('assignment_timeout_minutes');
            }

            if (!Schema::hasColumn('service_requests', 'assignment_notes')) {
                $table->text('assignment_notes')->nullable()->after('assignment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (Schema::hasColumn('service_requests', 'assignment_notes')) {
                $table->dropColumn('assignment_notes');
            }

            if (Schema::hasColumn('service_requests', 'assignment_method')) {
                $table->dropColumn('assignment_method');
            }
        });
    }
};
