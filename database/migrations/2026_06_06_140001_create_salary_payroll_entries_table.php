<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payroll_entries')) {
            return;
        }

        Schema::create('salary_payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_payroll_run_id')->constrained('salary_payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monthly_gross', 12, 2)->default(0);
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->json('earnings_breakdown')->nullable();
            $table->json('deductions_breakdown')->nullable();
            $table->decimal('pf_employee', 12, 2)->default(0);
            $table->decimal('pf_employer', 12, 2)->default(0);
            $table->decimal('esi_employee', 12, 2)->default(0);
            $table->decimal('esi_employer', 12, 2)->default(0);
            $table->decimal('pt_amount', 12, 2)->default(0);
            $table->enum('status', ['calculated', 'excluded', 'error'])->default('calculated');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['salary_payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payroll_entries');
    }
};
