<?php

namespace Database\Seeders;

use App\Models\IncentiveDeductionType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncentiveDeductionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Incentive', 'type' => 'earning', 'mode' => 'day'],
            ['name' => 'Incentive', 'type' => 'earning', 'mode' => 'amount'],
            ['name' => 'Adv / Karchi', 'type' => 'deduction', 'mode' => 'amount'],
            ['name' => 'Canteen', 'type' => 'deduction', 'mode' => 'amount'],
            ['name' => 'Colony', 'type' => 'deduction', 'mode' => 'amount'],
            ['name' => 'Mobile', 'type' => 'deduction', 'mode' => 'amount'],
            ['name' => 'TDS AMT', 'type' => 'deduction', 'mode' => 'amount'],
            ['name' => 'Instl. Amt', 'type' => 'deduction', 'mode' => 'amount'],
            ['name' => 'Laxmi Book', 'type' => 'deduction', 'mode' => 'amount'],
            ['name' => 'Sleeping', 'type' => 'deduction', 'mode' => 'day'],
            ['name' => 'Late Coming', 'type' => 'deduction', 'mode' => 'day'],
        ];

        foreach ($types as $type) {
            IncentiveDeductionType::updateOrCreate(
                ['name' => $type['name'], 'type' => $type['type']],
                array_merge($type, ['created_by' => 1])
            );
        }
    }
}
