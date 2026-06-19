<?php

namespace Database\Seeders;

use App\Models\SemesterOption;
use Illuminate\Database\Seeder;

class SemesterOptionSeeder extends Seeder
{
    public function run(): void
    {
        $records = [];

        for ($level = 1; $level <= 7; $level++) {
            for ($spill = 1; $spill <= 2; $spill++) {
                $records[] = [
                    'level' => $level,
                    'spill' => $spill,
                    'name' => sprintf('Level %d - Spill %d', $level, $spill),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach ($records as $record) {
            SemesterOption::updateOrCreate(
                ['level' => $record['level'], 'spill' => $record['spill']],
                ['name' => $record['name']]
            );
        }
    }
}
