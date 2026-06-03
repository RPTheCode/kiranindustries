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
        if (!Schema::hasTable('week_offs')) {
            Schema::create('week_offs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id');
                $table->enum('type', ['weekly', 'monthly'])->default('weekly');
                $table->json('settings'); // Store the selected days
                $table->integer('created_by');
                $table->timestamps();

                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_offs');
    }
};
