<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_payroll_entries', 'pf_admin_employer')) {
                    $table->decimal('pf_admin_employer', 12, 2)->default(0)->after('pf_epf_employer');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'pf_challan_ac1')) {
                    $table->decimal('pf_challan_ac1', 12, 2)->default(0)->after('pf_admin_employer');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'pf_challan_ac2')) {
                    $table->decimal('pf_challan_ac2', 12, 2)->default(0)->after('pf_challan_ac1');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'pf_challan_ac10')) {
                    $table->decimal('pf_challan_ac10', 12, 2)->default(0)->after('pf_challan_ac2');
                }
                if (! Schema::hasColumn('salary_payroll_entries', 'pf_challan_total')) {
                    $table->decimal('pf_challan_total', 12, 2)->default(0)->after('pf_challan_ac10');
                }
            });

            $this->backfillEntryChallanFields();
        }

        if (Schema::hasTable('salary_payroll_runs')) {
            Schema::table('salary_payroll_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_eps_employer')) {
                    $table->decimal('total_pf_eps_employer', 14, 2)->default(0)->after('total_pf_employer');
                }
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_epf_employer')) {
                    $table->decimal('total_pf_epf_employer', 14, 2)->default(0)->after('total_pf_eps_employer');
                }
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_admin_employer')) {
                    $table->decimal('total_pf_admin_employer', 14, 2)->default(0)->after('total_pf_epf_employer');
                }
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_challan_ac1')) {
                    $table->decimal('total_pf_challan_ac1', 14, 2)->default(0)->after('total_pf_admin_employer');
                }
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_challan_ac2')) {
                    $table->decimal('total_pf_challan_ac2', 14, 2)->default(0)->after('total_pf_challan_ac1');
                }
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_challan_ac10')) {
                    $table->decimal('total_pf_challan_ac10', 14, 2)->default(0)->after('total_pf_challan_ac2');
                }
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_challan_total')) {
                    $table->decimal('total_pf_challan_total', 14, 2)->default(0)->after('total_pf_challan_ac10');
                }
                if (! Schema::hasColumn('salary_payroll_runs', 'total_pt_amount')) {
                    $table->decimal('total_pt_amount', 14, 2)->default(0)->after('total_esi_employer');
                }
            });

            $this->backfillRunChallanTotals();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_payroll_entries')) {
            Schema::table('salary_payroll_entries', function (Blueprint $table) {
                foreach ([
                    'pf_challan_total',
                    'pf_challan_ac10',
                    'pf_challan_ac2',
                    'pf_challan_ac1',
                    'pf_admin_employer',
                ] as $column) {
                    if (Schema::hasColumn('salary_payroll_entries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('salary_payroll_runs')) {
            Schema::table('salary_payroll_runs', function (Blueprint $table) {
                foreach ([
                    'total_pt_amount',
                    'total_pf_challan_total',
                    'total_pf_challan_ac10',
                    'total_pf_challan_ac2',
                    'total_pf_challan_ac1',
                    'total_pf_admin_employer',
                    'total_pf_epf_employer',
                    'total_pf_eps_employer',
                ] as $column) {
                    if (Schema::hasColumn('salary_payroll_runs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function backfillEntryChallanFields(): void
    {
        if (! Schema::hasColumn('salary_payroll_entries', 'pf_challan_total')) {
            return;
        }

        DB::table('salary_payroll_entries')
            ->where('pf_employee', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($entries) {
                foreach ($entries as $entry) {
                    $admin = max(0, round((float) $entry->pf_employer - (float) $entry->pf_eps_employer - (float) $entry->pf_epf_employer, 0));
                    $ac1 = round((float) $entry->pf_employee + (float) $entry->pf_epf_employer, 0);
                    $ac2 = round((float) $entry->pf_eps_employer, 0);
                    $ac10 = $admin;
                    $total = $ac1 + $ac2 + $ac10;

                    DB::table('salary_payroll_entries')->where('id', $entry->id)->update([
                        'pf_admin_employer' => $admin,
                        'pf_challan_ac1' => $ac1,
                        'pf_challan_ac2' => $ac2,
                        'pf_challan_ac10' => $ac10,
                        'pf_challan_total' => $total,
                    ]);
                }
            });
    }

    private function backfillRunChallanTotals(): void
    {
        if (! Schema::hasColumn('salary_payroll_runs', 'total_pf_challan_total')) {
            return;
        }

        $runIds = DB::table('salary_payroll_runs')->pluck('id');

        foreach ($runIds as $runId) {
            $totals = DB::table('salary_payroll_entries')
                ->where('salary_payroll_run_id', $runId)
                ->where('status', 'calculated')
                ->selectRaw('
                    COALESCE(SUM(pf_eps_employer), 0) as eps,
                    COALESCE(SUM(pf_epf_employer), 0) as epf,
                    COALESCE(SUM(pf_admin_employer), 0) as admin,
                    COALESCE(SUM(pf_challan_ac1), 0) as ac1,
                    COALESCE(SUM(pf_challan_ac2), 0) as ac2,
                    COALESCE(SUM(pf_challan_ac10), 0) as ac10,
                    COALESCE(SUM(pf_challan_total), 0) as challan_total,
                    COALESCE(SUM(pt_amount), 0) as pt
                ')
                ->first();

            if (! $totals) {
                continue;
            }

            DB::table('salary_payroll_runs')->where('id', $runId)->update([
                'total_pf_eps_employer' => round((float) $totals->eps, 2),
                'total_pf_epf_employer' => round((float) $totals->epf, 2),
                'total_pf_admin_employer' => round((float) $totals->admin, 2),
                'total_pf_challan_ac1' => round((float) $totals->ac1, 2),
                'total_pf_challan_ac2' => round((float) $totals->ac2, 2),
                'total_pf_challan_ac10' => round((float) $totals->ac10, 2),
                'total_pf_challan_total' => round((float) $totals->challan_total, 2),
                'total_pt_amount' => round((float) $totals->pt, 2),
            ]);
        }
    }
};
