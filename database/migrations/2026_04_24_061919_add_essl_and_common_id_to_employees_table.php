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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('essl_id')->nullable()->after('employee_id');
            $table->string('common_id')->nullable()->after('essl_id');
            $table->string('emy_code')->nullable()->after('common_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['essl_id', 'common_id', 'emy_code']);
        });
    }
};
