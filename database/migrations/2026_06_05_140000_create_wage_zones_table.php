<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50);
            $table->string('state')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->default('India');
            $table->unsignedTinyInteger('working_days')->default(26);
            $table->boolean('status')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_zones');
    }
};
