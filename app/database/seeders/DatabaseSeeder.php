<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ExpenseCategorySeeder::class,
            ServiceSeeder::class,
            PositionSeeder::class,
            PositionServiceSeeder::class,
            Scenario\ScenarioSchoolSeeder::class,
            Scenario\ScenarioSchoolContractSeeder::class,
            Scenario\ScenarioTherapistSeeder::class,
            Scenario\ScenarioTherapistContractSeeder::class,
            Scenario\ScenarioStudentSeeder::class,
            Scenario\ScenarioSSASeeder::class,
            Scenario\ScenarioScheduleSeeder::class,
            Scenario\ScenarioSessionLogSeeder::class,
            Scenario\ScenarioInvoiceBillSeeder::class,
            Scenario\ScenarioExpenseSeeder::class,
            Scenario\ScenarioLeadSeeder::class,
        ]);
    }
}
