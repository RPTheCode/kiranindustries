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
        Schema::table('material_items', function (Blueprint $table) {
            // Drop existing global unique index on code safely
            $table->dropUnique('material_items_code_unique');

            // Add new columns
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            $table->string('status')->default('active')->after('rate');
            $table->unsignedBigInteger('created_by')->nullable()->after('status');

            // Set up composite unique index scoped by branch
            $table->unique(['branch_id', 'code']);

            // Set up foreign key relation to branches
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropUnique(['branch_id', 'code']);
            $table->dropColumn(['branch_id', 'status', 'created_by']);
            $table->unique('code');
        });
    }
};
