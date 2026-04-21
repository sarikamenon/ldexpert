<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('expense_categories')->updateOrInsert(
            ['id' => 10],
            [
                'name' => 'Therapist Payouts',
                'slug' => 'therapist-payouts',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('expense_categories')
            ->where('id', 10)
            ->where('slug', 'therapist-payouts')
            ->delete();
    }
};
