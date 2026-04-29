<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing chains were ordered by created_at, so copying created_at into
        // recorded_at preserves the historical ordering and balance_after values.
        // Future writers (LedgerService) set recorded_at from the real source date.
        DB::table('ledger_entries')
            ->whereNull('recorded_at')
            ->update(['recorded_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        DB::table('ledger_entries')->update(['recorded_at' => null]);
    }
};
