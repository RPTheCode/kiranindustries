<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('essl_logs', function (Blueprint $table) {
            $table->index('log_date', 'essl_logs_log_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('essl_logs', function (Blueprint $table) {
            $table->dropIndex('essl_logs_log_date_index');
        });
    }
};
