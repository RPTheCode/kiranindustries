<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DeductionType;
use Illuminate\Database\Seeder;

class DeductionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()->get(['id']);

        if ($branches->isEmpty()) {
            return;
        }

        $defaults = [
            ['name' => 'Late Coming', 'calculation_mode' => 'day', 'default_amount' => 0, 'sort_order' => 1],
            ['name' => 'Sleeping (Day)', 'calculation_mode' => 'day', 'default_amount' => 0, 'sort_order' => 2],
            ['name' => 'Sleeping (Night)', 'calculation_mode' => 'day', 'default_amount' => 0, 'sort_order' => 3],
            ['name' => 'Split', 'calculation_mode' => 'day', 'default_amount' => 0, 'sort_order' => 4],
            ['name' => 'Gate / Other', 'calculation_mode' => 'month', 'default_amount' => 0, 'sort_order' => 5],
            ['name' => 'Canteen', 'calculation_mode' => 'month', 'default_amount' => 50, 'sort_order' => 6],
            ['name' => 'Colony', 'calculation_mode' => 'month', 'default_amount' => 450, 'sort_order' => 7],
            ['name' => 'Laxmi Book', 'calculation_mode' => 'month', 'default_amount' => 0, 'sort_order' => 8],
        ];

        foreach ($branches as $branch) {
            foreach ($defaults as $item) {
                DeductionType::firstOrCreate(
                    [
                        'name' => $item['name'],
                        'branch_id' => $branch->id,
                    ],
                    array_merge($item, [
                        'status' => 'active',
                        'created_by' => 1,
                    ])
                );
            }
        }
    }
}
