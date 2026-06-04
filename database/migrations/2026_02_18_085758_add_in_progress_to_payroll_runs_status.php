<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE payroll_runs MODIFY COLUMN status ENUM('draft', 'processing', 'in_review', 'completed', 'cancelled') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE payroll_runs MODIFY COLUMN status ENUM('draft', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'draft'");
    }
};
