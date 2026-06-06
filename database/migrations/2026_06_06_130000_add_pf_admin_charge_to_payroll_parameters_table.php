<?php

use App\Database\Migrations\Concerns\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        if (! $this->tableExists('payroll_parameters')) {
            return;
        }

        $this->addColumnIfMissing('payroll_parameters', 'pf_admin_charge_pct', function (Blueprint $table) {
            $table->decimal('pf_admin_charge_pct', 5, 2)->nullable()->after('fpf_pct');
        });

        foreach (DB::table('payroll_parameters')->get() as $row) {
            if ($row->pf_admin_charge_pct === null) {
                DB::table('payroll_parameters')->where('id', $row->id)->update([
                    'pf_admin_charge_pct' => 1.00,
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->dropColumnIfExists('payroll_parameters', 'pf_admin_charge_pct');
    }
};
