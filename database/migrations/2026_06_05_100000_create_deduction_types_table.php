<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deduction_types')) {
            return;
        }

        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('default_amount', 10, 2)->default(0);
            $table->enum('calculation_mode', ['day', 'month'])->default('day');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        if (Schema::hasTable('monthly_incentive_details')) {
            Schema::table('monthly_incentive_details', function (Blueprint $table) {
                if (! Schema::hasColumn('monthly_incentive_details', 'deduction_type_id')) {
                    $table->unsignedBigInteger('deduction_type_id')->nullable()->after('type_id');
                    $table->foreign('deduction_type_id')
                        ->references('id')
                        ->on('deduction_types')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monthly_incentive_details')) {
            Schema::table('monthly_incentive_details', function (Blueprint $table) {
                if (Schema::hasColumn('monthly_incentive_details', 'deduction_type_id')) {
                    $table->dropForeign(['deduction_type_id']);
                    $table->dropColumn('deduction_type_id');
                }
            });
        }

        Schema::dropIfExists('deduction_types');
    }
};
