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
        Schema::table('biometric_attendances', function (Blueprint $table) {
            // Create a temporary index on employee_id so we can drop the unique one
            // without breaking the foreign key constraint requirement in InnoDB
            $table->index('employee_id', 'bio_att_temp_emp_index');
            
            $table->dropUnique('emp_date_unique');
            
            $table->foreignId('shift_slot_id')->nullable()->after('shift_code')->constrained('shift_slots')->onDelete('set null');
            
            $table->unique(['employee_id', 'attendance_date', 'shift_slot_id'], 'bio_att_emp_date_slot_unique');
            
            // Now we can drop the temp index as the new unique index covers employee_id
            $table->dropIndex('bio_att_temp_emp_index');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_attendances', function (Blueprint $table) {
            $table->index('employee_id', 'bio_att_temp_emp_index');
            $table->dropUnique('bio_att_emp_date_slot_unique');
            $table->dropConstrainedForeignId('shift_slot_id');
            $table->unique(['employee_id', 'attendance_date'], 'emp_date_unique');
            $table->dropIndex('bio_att_temp_emp_index');
        });
    }
};
