<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Rent', 'slug' => 'rent'],
            ['name' => 'Office Supplies', 'slug' => 'office-supplies'],
            ['name' => 'Insurance', 'slug' => 'insurance'],
            ['name' => 'Travel', 'slug' => 'travel'],
            ['name' => 'Software & Technology', 'slug' => 'software-technology'],
            ['name' => 'Professional Services', 'slug' => 'professional-services'],
            ['name' => 'Marketing & Advertising', 'slug' => 'marketing-advertising'],
            ['name' => 'Utilities', 'slug' => 'utilities'],
            ['name' => 'Miscellaneous', 'slug' => 'miscellaneous'],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
