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
        Schema::create('essl_logs', function (Blueprint $table) {
            $table->id();
            // ESSL DeviceLogId repeats each month (DeviceLogs_4_2026, DeviceLogs_5_2026, etc.)
            $table->bigInteger('device_log_id')->index();
            $table->dateTime('download_date')->nullable();
            $table->string('device_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Managed as the mapped internal User (Not Nullable)
            $table->dateTime('log_date')->nullable();
            $table->string('direction')->nullable();
            $table->string('att_direction')->nullable();
            $table->string('c1')->nullable();
            $table->string('c2')->nullable();
            $table->string('c3')->nullable();
            $table->string('c4')->nullable();
            $table->string('c5')->nullable();
            $table->string('c6')->nullable();
            $table->string('c7')->nullable();
            $table->string('work_code')->nullable();
            $table->integer('update_flag')->nullable();
            $table->string('file_name')->nullable();
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->dateTime('essl_created_date')->nullable();
            $table->dateTime('essl_last_modified_date')->nullable();
            $table->text('location_address')->nullable();
            $table->string('body_temperature')->nullable();
            $table->boolean('is_mask_on')->nullable();
            $table->timestamps();
            
            $table->index(['log_date', 'user_id']);
            $table->unique(['user_id', 'log_date', 'direction'], 'essl_logs_user_log_date_direction_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essl_logs');
    }
};
