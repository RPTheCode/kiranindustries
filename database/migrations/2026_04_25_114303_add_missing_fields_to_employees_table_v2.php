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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('phone_2')->nullable()->after('phone');
            $table->text('permanent_address')->nullable()->after('address_line_2');
            $table->string('driving_license')->nullable()->after('pan_card_number');
            $table->string('election_card')->nullable()->after('driving_license');
            $table->boolean('hod_flag')->default(false)->after('esic_flag');
            $table->decimal('ot_hours', 8, 2)->default(0)->after('ot_flag');
            $table->string('bank_type')->nullable()->after('bank_name');
            $table->string('lunch_time')->nullable()->after('shift_id');
            $table->string('week_off')->nullable()->after('lunch_time');
            $table->string('days')->nullable()->after('week_off');
            $table->decimal('education_allowance', 15, 2)->default(0)->after('medical_allowance');
            $table->string('employment_status')->nullable()->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'phone_2', 'permanent_address', 'driving_license', 'election_card', 
                'hod_flag', 'ot_hours', 'bank_type', 'lunch_time', 'week_off', 
                'days', 'education_allowance', 'employment_status'
            ]);
        });
    }
};
