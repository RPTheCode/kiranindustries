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
        Schema::create('biometric_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_attendance_id')
                  ->constrained('biometric_attendances')
                  ->onDelete('cascade');
            $table->dateTime('punch_time');
            $table->string('punch_type', 10); // 'IN' or 'OUT'
            $table->boolean('is_manual')->default(false);
            $table->foreignId('manually_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_attendance_logs');
    }
};
