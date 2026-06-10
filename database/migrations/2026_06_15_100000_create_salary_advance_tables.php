<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('salary_advance_requests')) {
            Schema::create('salary_advance_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->date('application_date');
                $table->string('advance_type', 20); // type_20, type_25
                $table->decimal('requested_amount', 15, 2);
                $table->decimal('approved_amount', 15, 2)->nullable();
                $table->string('amount_in_words')->nullable();
                $table->text('purpose');
                $table->decimal('present_salary_snapshot', 15, 2)->default(0);
                $table->decimal('earned_salary_snapshot', 15, 2)->default(0);
                $table->decimal('allowed_amount_snapshot', 15, 2)->default(0);
                $table->string('month_year', 7); // YYYY-MM
                $table->string('status', 30)->default('draft');
                $table->date('disbursement_date')->nullable();
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('recovered_in_salary_payroll_entry_id')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['employee_id', 'month_year']);
                $table->index(['status', 'disbursement_date']);
            });
        }

        if (Schema::hasTable('salary_payroll_entries') && ! Schema::hasColumn('salary_payroll_entries', 'advance_allocations')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                $table->json('advance_allocations')->nullable()->after('deductions_breakdown');
            });
        }

        if (Schema::hasTable('salary_advance_requests') && Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_advance_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_advance_requests', 'recovered_in_salary_payroll_entry_id')) {
                    return;
                }
                if (! $this->foreignKeyExists('salary_advance_requests', 'sar_recovered_sp_entry_fk')) {
                    $table->foreign('recovered_in_salary_payroll_entry_id', 'sar_recovered_sp_entry_fk')
                        ->references('id')
                        ->on('salary_payroll_entries')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_advance_requests')) {
            Schema::table('salary_advance_requests', function (Blueprint $table) {
                if ($this->foreignKeyExists('salary_advance_requests', 'sar_recovered_sp_entry_fk')) {
                    $table->dropForeign('sar_recovered_sp_entry_fk');
                }
            });
        }

        Schema::dropIfExists('salary_advance_requests');

        if (Schema::hasTable('salary_payroll_entries') && Schema::hasColumn('salary_payroll_entries', 'advance_allocations')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                $table->dropColumn('advance_allocations');
            });
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $name, 'FOREIGN KEY']
        );

        return count($result) > 0;
    }
};
