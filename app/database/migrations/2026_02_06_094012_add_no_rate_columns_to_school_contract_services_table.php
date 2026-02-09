<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_contract_services', function (Blueprint $table) {
            $table->decimal('no_show_rate', 10, 2)->after('rate_type');
            $table->char('no_show_rate_type', 1)->after('no_show_rate');
        });
    }

    public function down(): void
    {
        Schema::table('school_contract_services', function (Blueprint $table) {
            $table->dropColumn(['no_show_rate', 'no_show_rate_type']);
        });
    }
};
