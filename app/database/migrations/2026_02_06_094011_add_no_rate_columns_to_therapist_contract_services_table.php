<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapist_contract_services', function (Blueprint $table) {
            $table->decimal('no_rate', 10, 2)->after('rate_type');
            $table->char('no_rate_type', 1)->after('no_rate');
        });
    }

    public function down(): void
    {
        Schema::table('therapist_contract_services', function (Blueprint $table) {
            $table->dropColumn(['no_rate', 'no_rate_type']);
        });
    }
};
