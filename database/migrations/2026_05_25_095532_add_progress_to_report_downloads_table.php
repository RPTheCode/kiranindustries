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
        if (! Schema::hasTable('report_downloads') || Schema::hasColumn('report_downloads', 'progress')) {
            return;
        }

        Schema::table('report_downloads', function (Blueprint $table) {
            $table->integer('progress')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_downloads', function (Blueprint $table) {
            $table->dropColumn('progress');
        });
    }
};
