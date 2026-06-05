<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monthly_gross', 12, 2)->default(0);
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->json('components')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('revision_type', ['joining', 'increment', 'promotion', 'correction'])->default('increment');
            $table->decimal('previous_gross', 12, 2)->nullable();
            $table->decimal('increment_percentage', 8, 2)->nullable();
            $table->decimal('increment_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
            $table->index(['employee_id', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_revisions');
    }
};
