<?php

use App\Database\Migrations\Concerns\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        if (! $this->tableExists('payroll_parameters')) {
            return;
        }

        $this->addColumnIfMissing('payroll_parameters', 'pf_employee_pct', function (Blueprint $table) {
            $table->decimal('pf_employee_pct', 5, 2)->nullable()->after('fpf_pct');
        });
        $this->addColumnIfMissing('payroll_parameters', 'pf_employer_pct', function (Blueprint $table) {
            $table->decimal('pf_employer_pct', 5, 2)->nullable()->after('pf_employee_pct');
        });
        $this->addColumnIfMissing('payroll_parameters', 'esic_employee_pct', function (Blueprint $table) {
            $table->decimal('esic_employee_pct', 5, 2)->nullable()->after('esic_pct');
        });
        $this->addColumnIfMissing('payroll_parameters', 'esic_employer_pct', function (Blueprint $table) {
            $table->decimal('esic_employer_pct', 5, 2)->nullable()->after('esic_employee_pct');
        });
        $this->addColumnIfMissing('payroll_parameters', 'esic_wage_limit', function (Blueprint $table) {
            $table->decimal('esic_wage_limit', 10, 2)->nullable()->after('esic_employer_pct');
        });

        foreach (DB::table('payroll_parameters')->get() as $row) {
            $employerPf = ((float) ($row->pf_pct ?? 0)) + ((float) ($row->fpf_pct ?? 0));
            if ($employerPf <= 0) {
                $employerPf = (float) ($row->total_pf_pct ?? 12);
            }

            DB::table('payroll_parameters')->where('id', $row->id)->update([
                'pf_employee_pct' => $row->pf_employee_pct ?? $row->total_pf_pct ?? 12,
                'pf_employer_pct' => $row->pf_employer_pct ?? $employerPf,
                'esic_employee_pct' => $row->esic_employee_pct ?? $row->esic_pct ?? 0.75,
                'esic_employer_pct' => $row->esic_employer_pct ?? 3.25,
                'esic_wage_limit' => $row->esic_wage_limit ?? 21000,
            ]);
        }
    }

    public function down(): void
    {
        if (! $this->tableExists('payroll_parameters')) {
            return;
        }

        foreach (['esic_wage_limit', 'esic_employer_pct', 'esic_employee_pct', 'pf_employer_pct', 'pf_employee_pct'] as $column) {
            $this->dropColumnIfExists('payroll_parameters', $column);
        }
    }
};
