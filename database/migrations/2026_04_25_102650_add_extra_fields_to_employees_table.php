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
            // Personal Details
            $table->string('father_name')->nullable()->after('user_id');
            $table->date('confirm_date')->nullable()->after('date_of_joining');
            $table->string('place')->nullable()->after('confirm_date');
            
            // Flags
            $table->boolean('pf_flag')->default(false)->after('pf_id');
            $table->boolean('esic_flag')->default(false)->after('esi_id');
            $table->boolean('ptax_flag')->default(false)->after('pt_deduction');
            $table->boolean('bonus_flag')->default(false)->after('ptax_flag');
            $table->boolean('ot_flag')->default(false)->after('bonus_flag');
            
            // Extra Salary Components
            $table->decimal('gross_salary', 15, 2)->default(0)->after('medical_allowance');
            $table->decimal('conveyance_allowance', 15, 2)->default(0)->after('gross_salary');
            $table->decimal('special_allowance', 15, 2)->default(0)->after('conveyance_allowance');
            $table->decimal('ot_rate', 15, 2)->default(0)->after('special_allowance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'father_name', 'confirm_date', 'place',
                'pf_flag', 'esic_flag', 'ptax_flag', 'bonus_flag', 'ot_flag',
                'gross_salary', 'conveyance_allowance', 'special_allowance', 'ot_rate'
            ]);
        });
    }
};
