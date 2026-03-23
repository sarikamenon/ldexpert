<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('therapist_contract_services', function (Blueprint $table) {
            $table->decimal('no_show_rate', 10, 2)->nullable()->change();
            $table->char('no_show_rate_type', 1)->nullable()->change();
        });

        Schema::table('school_contract_services', function (Blueprint $table) {
            $table->decimal('no_show_rate', 10, 2)->nullable()->change();
            $table->char('no_show_rate_type', 1)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapist_contract_services', function (Blueprint $table) {
            $table->decimal('no_show_rate', 10, 2)->nullable(false)->default(0)->change();
            $table->char('no_show_rate_type', 1)->nullable(false)->default('H')->change();
        });

        Schema::table('school_contract_services', function (Blueprint $table) {
            $table->decimal('no_show_rate', 10, 2)->nullable(false)->default(0)->change();
            $table->char('no_show_rate_type', 1)->nullable(false)->default('H')->change();
        });
    }
};
