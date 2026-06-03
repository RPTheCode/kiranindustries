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
        Schema::create('shift_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->string('slot_name'); // e.g., DAY / NIGHT
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('grace_before_in')->default(60);
            $table->integer('grace_after_out')->default(60);
            $table->integer('half_day_minutes')->default(240);
            $table->integer('full_day_minutes')->default(480);
            $table->integer('double_shift_minutes')->default(960);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_slots');
    }
};
