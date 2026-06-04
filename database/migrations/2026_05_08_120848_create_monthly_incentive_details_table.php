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
        Schema::create('monthly_incentive_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monthly_incentive_entry_id');
            $table->unsignedBigInteger('type_id')->nullable();
            $table->string('name')->nullable();
            $table->enum('type', ['earning', 'deduction'])->default('earning');
            $table->enum('mode', ['amount', 'day'])->default('amount');
            $table->decimal('value', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('monthly_incentive_entry_id', 'mi_entry_id_foreign')
                ->references('id')->on('monthly_incentive_entries')
                ->onDelete('cascade');
            $table->foreign('type_id')
                ->references('id')->on('incentive_deduction_types')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_incentive_details');
    }
};
