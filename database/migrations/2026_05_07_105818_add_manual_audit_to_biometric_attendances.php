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
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('manual_by')->nullable()->after('status');
            $table->text('manual_remarks')->nullable()->after('manual_by');
            
            $table->foreign('manual_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->dropForeign(['manual_by']);
            $table->dropColumn(['manual_by', 'manual_remarks']);
        });
    }
};
