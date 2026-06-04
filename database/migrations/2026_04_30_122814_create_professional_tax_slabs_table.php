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
        Schema::create('professional_tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_amt', 10, 2);
            $table->decimal('max_amt', 10, 2)->nullable();
            $table->decimal('pt_amt', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_tax_slabs');
    }
};
