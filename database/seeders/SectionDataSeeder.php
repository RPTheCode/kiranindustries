<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectionDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['code' => 'DIR', 'name' => 'DIRECTOR'],
            ['code' => 'JARI', 'name' => 'JARI'],
            ['code' => 'JD KIM OTHER', 'name' => 'JARI DIV. OTHER'],
            ['code' => 'JARI KIM', 'name' => 'JARI KIM'],
            ['code' => 'KSP', 'name' => 'KSP'],
            ['code' => 'OTH', 'name' => 'OTHER'],
            ['code' => 'OTHEXM', 'name' => 'OTHER EXEMPTED'],
            ['code' => 'PAL', 'name' => 'PALSANA'],
            ['code' => 'RO', 'name' => 'RO'],
            ['code' => 'STITCHING KSP', 'name' => 'STITCHING'],
            ['code' => 'STITCHINGKSP', 'name' => 'STITCHING'],
            ['code' => 'THRD', 'name' => 'THREAD'],
            ['code' => 'TRAINEE', 'name' => 'TRAINEE'],
            ['code' => 'YD KIM', 'name' => 'YARN DIV. KIM'],
            ['code' => 'YD OTHER', 'name' => 'YARN DIV. OTHER'],
            ['code' => 'YD OTHER EXA', 'name' => 'YARN DIV. OTHER EXEM'],
        ];

        foreach ($data as $d) {
            \App\Models\Section::updateOrCreate(
                ['code' => $d['code']],
                ['name' => $d['name'], 'status' => 'active', 'created_by' => 1]
            );
        }
    }
}
