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
        Schema::create('biometric_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('employee_code');
            $table->foreignId('department_id')->nullable()->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();
            
            $table->date('attendance_date')->index();
            $table->string('shift_code')->nullable();
            
            $table->dateTime('in_time')->nullable();
            $table->dateTime('out_time')->nullable();
            
            $table->integer('in_count')->default(0);
            $table->integer('out_count')->default(0);
            $table->integer('punch_count')->default(0);
            
            $table->integer('total_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_minutes')->default(0);
            $table->integer('ot_minutes')->default(0);
            
            $table->decimal('duty_value', 3, 1)->default(0.0);
            
            $table->enum('status', ['P', 'A', 'MIS', 'H', 'W'])->default('A');
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_weekly_off')->default(false);
            $table->boolean('is_manual')->default(false);
            $table->text('remarks')->nullable();
            
            $table->unique(['employee_id', 'attendance_date'], 'emp_date_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_attendances');
    }
};
