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
            // first drop foreign key
            $table->dropForeign(['skill_id']);

            // change column type
            // Note: DB::statement might be needed for some DBs if change() doesn't work for FK columns directly without dropping first.
            // We dropped FK above.
            $table->json('skill_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // We can't easily revert JSON array to single ID without data loss logic
            // But structure-wise:
            $table->unsignedBigInteger('skill_id')->nullable()->change();
            $table->foreign('skill_id')->references('id')->on('skills')->onDelete('set null');
        });
    }
};
