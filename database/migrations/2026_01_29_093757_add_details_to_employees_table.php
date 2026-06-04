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
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('skill_id')->nullable()->constrained('skills')->onDelete('set null');
            $table->string('pf_number')->nullable();
            $table->string('uan_number')->nullable();
            $table->string('esic_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['skill_id']);
            $table->dropColumn(['skill_id', 'pf_number', 'uan_number', 'esic_number']);
        });
    }
};
