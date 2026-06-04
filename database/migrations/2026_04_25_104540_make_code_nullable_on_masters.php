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
        Schema::table('sections', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });
        Schema::table('designations', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });
        Schema::table('designations', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });
    }
};
