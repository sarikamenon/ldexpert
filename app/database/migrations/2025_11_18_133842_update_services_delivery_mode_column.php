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
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'delivery_modes')) {
                $table->dropColumn('delivery_modes');
            }

            if (! Schema::hasColumn('services', 'delivery_mode')) {
                $table->string('delivery_mode', 32)
                    ->default('virtual')
                    ->after('frequency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
                if (Schema::hasColumn('services', 'delivery_mode')) {
                    $table->dropColumn('delivery_mode');
                }

                if (! Schema::hasColumn('services', 'delivery_modes')) {
                    $table->json('delivery_modes')->nullable()->after('frequency');
                }
        });
    }
};
