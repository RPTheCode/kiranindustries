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
            $table->string('height', 20)->nullable()->after('blood_group');
            $table->decimal('pf_basic_salary', 15, 2)->nullable()->after('basic_salary');
            $table->decimal('it_amount', 15, 2)->nullable()->after('gross_salary');
            if (!Schema::hasColumn('employees', 'ifsc_code')) {
                $table->string('ifsc_code', 20)->nullable()->after('bank_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            //
        });
    }
};
