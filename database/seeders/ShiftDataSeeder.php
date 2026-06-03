<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $shifts = [
            ['short_code' => 'B', 'name' => 'BUS SHIFT', 'start_time' => '10:00:00', 'end_time' => '18:00:00'],
            ['short_code' => 'G', 'name' => 'GENERAL SHIFT', 'start_time' => '10:00:00', 'end_time' => '18:00:00'],
            ['short_code' => 'H', 'name' => 'HUB SHIFT', 'start_time' => '10:00:00', 'end_time' => '18:00:00'],
            ['short_code' => 'HOD', 'name' => 'HOD', 'start_time' => '09:30:00', 'end_time' => '17:30:00'],
            ['short_code' => 'M', 'name' => 'MULTI SHIFT', 'start_time' => '08:00:00', 'end_time' => '16:00:00'],
            ['short_code' => 'PD', 'name' => 'DAY SHIFT', 'start_time' => '08:00:00', 'end_time' => '16:00:00'],
            ['short_code' => 'PG', 'name' => 'GENERAL SHIFT', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
            ['short_code' => 'PN', 'name' => 'NIGHT SHIFT', 'start_time' => '20:00:00', 'end_time' => '04:00:00', 'is_night_shift' => 1],
            ['short_code' => 'PP', 'name' => 'PACKING SHIFT', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
            ['short_code' => 'RO', 'name' => 'RO', 'start_time' => '10:00:00', 'end_time' => '18:00:00'],
        ];

        foreach ($shifts as $shiftData) {
            Shift::updateOrCreate(
                ['short_code' => $shiftData['short_code']],
                array_merge($shiftData, [
                    'created_by' => 1,
                    'branch_id' => 1,
                    'status' => 1,
                ])
            );
        }
    }
}
