<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deduction_types')) {
            return;
        }

        if (! Schema::hasColumn('deduction_types', 'amount_type')) {
            Schema::table('deduction_types', function (Blueprint $table) {
                $table->string('amount_type', 20)->default('fixed')->after('default_amount');
            });
        }

        if (! Schema::hasTable('deduction_type_category_amounts')) {
            Schema::create('deduction_type_category_amounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deduction_type_id');
                $table->unsignedBigInteger('category_id');
                $table->decimal('amount', 10, 2)->default(0);
                $table->timestamps();

                $table->foreign('deduction_type_id')
                    ->references('id')
                    ->on('deduction_types')
                    ->cascadeOnDelete();
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->cascadeOnDelete();
                $table->unique(['deduction_type_id', 'category_id'], 'deduction_type_category_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_type_category_amounts');

        Schema::table('deduction_types', function (Blueprint $table) {
            if (Schema::hasColumn('deduction_types', 'amount_type')) {
                $table->dropColumn('amount_type');
            }
        });
    }
};
