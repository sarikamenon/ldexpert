<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ssa_additional_services') && ! Schema::hasTable('ssa_services')) {
            Schema::rename('ssa_additional_services', 'ssa_services');
        }

        Schema::table('ssa_services', function (Blueprint $table) {
            if (! Schema::hasColumn('ssa_services', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('service_id');
            }
        });

        DB::table('ssa_services')->update(['is_primary' => false]);

        $now = now();
        $ssas = DB::table('service_support_agreements')
            ->select('id', 'primary_service_id')
            ->whereNotNull('primary_service_id')
            ->get();

        foreach ($ssas as $ssa) {
            DB::table('ssa_services')->updateOrInsert(
                [
                    'ssa_id' => $ssa->id,
                    'service_id' => $ssa->primary_service_id,
                ],
                [
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ssa_services', 'is_primary')) {
            DB::table('ssa_services')
                ->where('is_primary', true)
                ->delete();

            Schema::table('ssa_services', function (Blueprint $table) {
                $table->dropColumn('is_primary');
            });
        }

        if (Schema::hasTable('ssa_services')) {
            Schema::rename('ssa_services', 'ssa_additional_services');
        }
    }
};

