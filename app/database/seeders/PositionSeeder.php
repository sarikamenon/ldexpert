<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = ['SLP', 'OT', 'PT', 'LCSW', 'SW', 'BCBA', 'RBT'];

        foreach ($positions as $name) {
            Position::firstOrCreate(
                ['name' => $name],
                ['status' => 'active'],
            );
        }
    }
}
