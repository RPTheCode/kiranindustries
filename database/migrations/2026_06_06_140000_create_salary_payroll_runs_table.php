<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payroll_runs')) {
            return;
        }

        Schema::create('salary_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('financial_year', 20);
            $table->string('month_year', 7);
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->string('title');
            $table->enum('scope_mode', ['all', 'category', 'shift', 'employee'])->default('all');
            $table->json('scope_filters')->nullable();
            $table->enum('status', ['draft', 'calculated', 'finalized'])->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_pf_employee', 14, 2)->default(0);
            $table->decimal('total_pf_employer', 14, 2)->default(0);
            $table->decimal('total_esi_employee', 14, 2)->default(0);
            $table->decimal('total_esi_employer', 14, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'month_year']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payroll_runs');
    }
};
