<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payroll_payslips')) {
            return;
        }

        Schema::create('salary_payroll_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_payroll_entry_id')->unique()->constrained('salary_payroll_entries')->cascadeOnDelete();
            $table->foreignId('salary_payroll_run_id')->constrained('salary_payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('payslip_number')->unique();
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salary_payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payroll_payslips');
    }
};
