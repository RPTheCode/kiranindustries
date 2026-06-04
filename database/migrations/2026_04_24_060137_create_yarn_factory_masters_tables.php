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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('bank_masters', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('ifsc_code')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('pf_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('percentage_employee', 5, 2)->default(12.00);
            $table->decimal('percentage_employer', 5, 2)->default(12.00);
            $table->decimal('limit', 15, 2)->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('esi_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('percentage_employee', 5, 2)->default(0.75);
            $table->decimal('percentage_employer', 5, 2)->default(3.25);
            $table->decimal('limit', 15, 2)->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esi_masters');
        Schema::dropIfExists('pf_masters');
        Schema::dropIfExists('bank_masters');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('sections');
    }
};
