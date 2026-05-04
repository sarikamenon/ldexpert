<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table): void {
            // recorded_at = "when the underlying transaction occurred" (user-controlled, may be backdated).
            // Distinct from created_at = "when the row was inserted" (DB-controlled).
            // Naming gotcha: recorded_by_id is "who recorded the row"; recorded_at is the txn time.
            $table->timestamp('recorded_at')->nullable()->after('balance_after');

            $table->index(
                ['ledgerable_type', 'ledgerable_id', 'recorded_at', 'id'],
                'ledger_entries_account_recorded_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->dropIndex('ledger_entries_account_recorded_idx');
            $table->dropColumn('recorded_at');
        });
    }
};
