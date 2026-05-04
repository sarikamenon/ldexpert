<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ExpenseCategorySeeder::class,
            ServiceSeeder::class,
            ServiceAliasSeeder::class,
            PositionSeeder::class,
            PositionServiceSeeder::class,
        ]);
    }
}
