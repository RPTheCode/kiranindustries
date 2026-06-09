<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('skills', 'govt_wage_per_day')) {
            return;
        }

        $skillsWithWages = DB::table('skills')
            ->where(function ($q) {
                $q->whereNotNull('govt_wage_per_day')
                    ->orWhereNotNull('govt_wage_per_month');
            })
            ->get();

        if ($skillsWithWages->isNotEmpty()) {
            $creatorId = $skillsWithWages->first()->created_by;

            $zoneId = DB::table('wage_zones')->insertGetId([
                'name' => 'Default Wage Zone',
                'code' => 'DEFAULT',
                'state' => null,
                'region' => null,
                'country' => 'India',
                'working_days' => 26,
                'status' => true,
                'notes' => 'Auto-created while migrating skill wage fields.',
                'created_by' => $creatorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($skillsWithWages as $skill) {
                if (! $skill->govt_wage_per_day && ! $skill->govt_wage_per_month) {
                    continue;
                }

                DB::table('skill_wage_rates')->insert([
                    'skill_id' => $skill->id,
                    'wage_zone_id' => $zoneId,
                    'wage_per_day' => $skill->govt_wage_per_day,
                    'wage_per_month' => $skill->govt_wage_per_month,
                    'effective_from' => $skill->wage_effective_from,
                    'created_by' => $skill->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn(['govt_wage_per_day', 'govt_wage_per_month', 'wage_effective_from']);
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (! Schema::hasColumn('skills', 'govt_wage_per_day')) {
                $table->decimal('govt_wage_per_day', 10, 2)->nullable()->after('code');
                $table->decimal('govt_wage_per_month', 10, 2)->nullable()->after('govt_wage_per_day');
                $table->date('wage_effective_from')->nullable()->after('govt_wage_per_month');
            }
        });
    }
};
