<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('salary_loan_requests')) {
            Schema::create('salary_loan_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->date('application_date');
                $table->decimal('requested_amount', 15, 2);
                $table->decimal('approved_amount', 15, 2)->nullable();
                $table->unsignedTinyInteger('installment_count')->default(3);
                $table->decimal('installment_amount', 15, 2)->nullable();
                $table->string('amount_in_words')->nullable();
                $table->text('purpose');
                $table->decimal('present_salary_snapshot', 15, 2)->default(0);
                $table->string('month_year', 7);
                $table->string('deduction_start_month', 7)->nullable();
                $table->string('status', 30)->default('draft');
                $table->date('disbursement_date')->nullable();
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['employee_id', 'status']);
                $table->index(['status', 'disbursement_date']);
            });
        }

        if (! Schema::hasTable('salary_loan_guarantors')) {
            Schema::create('salary_loan_guarantors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('salary_loan_request_id');
                $table->unsignedBigInteger('guarantor_employee_id')->nullable();
                $table->string('name');
                $table->string('employee_code')->nullable();
                $table->string('department')->nullable();
                $table->unsignedTinyInteger('sort_order')->default(1);
                $table->timestamps();

                $table->foreign('salary_loan_request_id')
                    ->references('id')
                    ->on('salary_loan_requests')
                    ->cascadeOnDelete();
                $table->foreign('guarantor_employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('salary_loan_installments')) {
            Schema::create('salary_loan_installments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('salary_loan_request_id');
                $table->unsignedTinyInteger('installment_no');
                $table->string('due_month', 7);
                $table->decimal('amount', 15, 2);
                $table->string('status', 20)->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('recovered_in_salary_payroll_entry_id')->nullable();
                $table->timestamps();

                $table->foreign('salary_loan_request_id')
                    ->references('id')
                    ->on('salary_loan_requests')
                    ->cascadeOnDelete();
                $table->unique(['salary_loan_request_id', 'installment_no'], 'sli_request_no_unique');
                $table->index(['status', 'due_month']);
            });
        }

        if (Schema::hasTable('salary_payroll_entries') && ! Schema::hasColumn('salary_payroll_entries', 'loan_allocations')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                $table->json('loan_allocations')->nullable()->after('advance_allocations');
            });
        }

        if (Schema::hasTable('salary_loan_installments') && Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_loan_installments', function (Blueprint $table) {
                if (! $this->foreignKeyExists('salary_loan_installments', 'sli_recovered_sp_entry_fk')) {
                    $table->foreign('recovered_in_salary_payroll_entry_id', 'sli_recovered_sp_entry_fk')
                        ->references('id')
                        ->on('salary_payroll_entries')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_loan_installments')) {
            Schema::table('salary_loan_installments', function (Blueprint $table) {
                if ($this->foreignKeyExists('salary_loan_installments', 'sli_recovered_sp_entry_fk')) {
                    $table->dropForeign('sli_recovered_sp_entry_fk');
                }
            });
        }

        Schema::dropIfExists('salary_loan_installments');
        Schema::dropIfExists('salary_loan_guarantors');
        Schema::dropIfExists('salary_loan_requests');

        if (Schema::hasTable('salary_payroll_entries') && Schema::hasColumn('salary_payroll_entries', 'loan_allocations')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                $table->dropColumn('loan_allocations');
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
