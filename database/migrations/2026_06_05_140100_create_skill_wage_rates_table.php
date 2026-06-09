<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_wage_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->foreignId('wage_zone_id')->constrained('wage_zones')->cascadeOnDelete();
            $table->decimal('wage_per_day', 10, 2)->nullable();
            $table->decimal('wage_per_month', 10, 2)->nullable();
            $table->date('effective_from')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['skill_id', 'wage_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_wage_rates');
    }
};
