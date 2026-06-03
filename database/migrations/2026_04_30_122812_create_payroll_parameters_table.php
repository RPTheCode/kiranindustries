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
        Schema::create('payroll_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 20)->nullable();
            $table->decimal('total_pf_pct', 5, 2)->default(12.00);
            $table->decimal('pf_pct', 5, 2)->default(8.33);
            $table->decimal('fpf_pct', 5, 2)->default(3.67);
            $table->decimal('max_pf_amount', 10, 2)->default(15000);
            $table->decimal('esic_pct', 5, 2)->default(0.75);
            $table->decimal('karchi_pct', 5, 2)->default(0.00);
            $table->decimal('bonus_pct', 5, 2)->default(3.00);
            $table->decimal('bonus_max_limit', 10, 2)->default(7800);
            $table->integer('max_el')->default(7);
            $table->integer('max_sl')->default(7);
            $table->integer('max_cl')->default(7);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_parameters');
    }
};
