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
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('designation_id')->constrained('sections')->onDelete('set null');
            $table->foreignId('category_id')->nullable()->after('section_id')->constrained('categories')->onDelete('set null');
            $table->string('po_status')->nullable()->after('employment_type'); // Permanent / Off-the-record
            $table->boolean('daily_option')->default(false)->after('po_status');
            $table->integer('working_days')->nullable()->after('daily_option');
            
            // Education & Experience
            $table->string('education')->nullable()->after('gender');
            $table->string('experience')->nullable()->after('education');
            $table->string('aadhar_card_number')->nullable()->after('experience');
            $table->string('pan_card_number')->nullable()->after('aadhar_card_number');
            $table->string('blood_group')->nullable()->after('pan_card_number');
            
            // Loan Details
            $table->decimal('loan_total_amount', 15, 2)->default(0)->after('esic_number');
            $table->decimal('loan_installment_amount', 15, 2)->default(0)->after('loan_total_amount');
            $table->string('loan_period')->nullable()->after('loan_installment_amount');
            
            // Nominee Details
            $table->string('nominee_name')->nullable()->after('loan_period');
            $table->string('nominee_account_number')->nullable()->after('nominee_name');
            
            // Salary Components
            $table->decimal('basic_salary', 15, 2)->default(0)->after('nominee_account_number');
            $table->decimal('lta_allowance', 15, 2)->default(0)->after('basic_salary');
            $table->decimal('hra_allowance', 15, 2)->default(0)->after('lta_allowance');
            $table->decimal('pt_deduction', 15, 2)->default(0)->after('hra_allowance');
            $table->decimal('other_allowance', 15, 2)->default(0)->after('pt_deduction');
            $table->decimal('medical_allowance', 15, 2)->default(0)->after('other_allowance');
            
            // Master selection
            $table->foreignId('bank_id')->nullable()->after('bank_branch')->constrained('bank_masters')->onDelete('set null');
            $table->foreignId('pf_id')->nullable()->after('pf_number')->constrained('pf_masters')->onDelete('set null');
            $table->foreignId('esi_id')->nullable()->after('esic_number')->constrained('esi_masters')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['pf_id']);
            $table->dropForeign(['esi_id']);
            
            $table->dropColumn([
                'section_id', 'category_id', 'po_status', 'daily_option', 'working_days',
                'education', 'experience', 'aadhar_card_number', 'pan_card_number', 'blood_group',
                'loan_total_amount', 'loan_installment_amount', 'loan_period',
                'nominee_name', 'nominee_account_number',
                'basic_salary', 'lta_allowance', 'hra_allowance', 'pt_deduction', 'other_allowance', 'medical_allowance',
                'bank_id', 'pf_id', 'esi_id'
            ]);
        });
    }
};
