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
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn([
                'start_time',
                'end_time',
                'break_duration',
                'break_start_time',
                'break_end_time',
                'grace_period',
                'is_night_shift'
            ]);
        });

        Schema::table('shift_slots', function (Blueprint $table) {
            $table->dropColumn([
                'half_day_minutes',
                'full_day_minutes',
                'double_shift_minutes'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('break_duration')->default(0);
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->integer('grace_period')->default(0);
            $table->boolean('is_night_shift')->default(false);
        });

        Schema::table('shift_slots', function (Blueprint $table) {
            $table->integer('half_day_minutes')->default(240);
            $table->integer('full_day_minutes')->default(480);
            $table->integer('double_shift_minutes')->default(960);
        });
    }
};
