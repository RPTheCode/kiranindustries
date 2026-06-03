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
        if (!Schema::hasColumn('payslips', 'salary_status')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->enum('salary_status', ['released', 'hold'])->default('released')->after('status');
                $table->dateTime('released_at')->nullable()->after('salary_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['salary_status', 'released_at']);
        });
    }
};
