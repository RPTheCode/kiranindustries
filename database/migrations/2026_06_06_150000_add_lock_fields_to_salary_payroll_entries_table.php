<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_payroll_entries', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('error_message');
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('is_locked')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('salary_payroll_entries', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('locked_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_payroll_entries', function (Blueprint $table) {
            if (Schema::hasColumn('salary_payroll_entries', 'locked_by')) {
                $table->dropConstrainedForeignId('locked_by');
            }
            if (Schema::hasColumn('salary_payroll_entries', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
            if (Schema::hasColumn('salary_payroll_entries', 'is_locked')) {
                $table->dropColumn('is_locked');
            }
        });
    }
};
