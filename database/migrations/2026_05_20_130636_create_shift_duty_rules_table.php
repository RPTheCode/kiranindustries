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
        Schema::create('shift_duty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_slot_id')->constrained()->onDelete('cascade');
            $table->string('rule_name');
            $table->integer('min_minutes');
            $table->integer('max_minutes');
            $table->string('status', 20); // e.g. P, HD, A
            $table->decimal('duty_value', 5, 2); // e.g. 1.0, 0.5, 2.0
            $table->string('color', 50)->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_duty_rules');
    }
};
