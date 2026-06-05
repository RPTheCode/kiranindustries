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
        if (! Schema::hasTable('report_downloads')) {
            Schema::create('report_downloads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->string('report_name');
                $table->string('file_path')->nullable();
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->json('filters')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_downloads');
    }
};
