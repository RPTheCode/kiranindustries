<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftDutyRuleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('shift_duty_rules')->truncate();
        DB::table('shift_slots')->truncate();
        DB::table('shifts')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $company = User::where('type', 'company')->first();

        if (!$company) {
            $this->command->error('Company not found. Please seed it first.');
            return;
        }

        $shiftTemplates = [
            ['short_code' => 'PD', 'name' => 'DAY SHIFT', 'is_multi' => false],
            ['short_code' => 'PG', 'name' => 'GENERAL SHIFT', 'is_multi' => false],
            ['short_code' => 'PN', 'name' => 'NIGHT SHIFT', 'is_multi' => false],
            ['short_code' => 'PP', 'name' => 'PACKING SHIFT', 'is_multi' => false],
            ['short_code' => 'RO', 'name' => 'RO', 'is_multi' => false],
            ['short_code' => 'B', 'name' => 'BUS SHIFT', 'is_multi' => false],
            ['short_code' => 'G', 'name' => 'GENERAL SHIFT', 'is_multi' => false],
            ['short_code' => 'H', 'name' => 'HUB SHIFT', 'is_multi' => false],
            ['short_code' => 'HOD', 'name' => 'HOD', 'is_multi' => false],
            ['short_code' => 'M', 'name' => 'MULTI SHIFT', 'is_multi' => true],
        ];

        foreach ($shiftTemplates as $data) {
            $shift = Shift::create([
                'short_code' => $data['short_code'],
                'name' => $data['name'],
                'is_multi' => $data['is_multi'],
                'branch_id' => null,
                'created_by' => 1,
                'status' => 'active',
            ]);

            $slotTemplates = $shift->is_multi
                ? ShiftDutyRuleService::defaultMultiSlots()
                : (ShiftDutyRuleService::defaultFixedSlot($shift->short_code) ? [ShiftDutyRuleService::defaultFixedSlot($shift->short_code)] : []);

            foreach ($slotTemplates as $index => $slotData) {
                $slotData['priority'] = $slotData['priority'] ?? ($index + 1);
                $slot = $shift->slots()->create($slotData);
                ShiftDutyRuleService::syncSlotDutyRules($slot);
            }
        }

        $this->command->info('Master Global Admin Shifts seeded successfully!');
    }
}
