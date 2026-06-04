<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change the status column from ENUM to VARCHAR to support dynamic statuses like 'HD' (Half Day)
        DB::statement("ALTER TABLE `biometric_attendances` MODIFY `status` VARCHAR(10) NOT NULL DEFAULT 'A'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to ENUM
        DB::statement("ALTER TABLE `biometric_attendances` MODIFY `status` ENUM('P','A','MIS','H','W') NOT NULL DEFAULT 'A'");
    }
};
