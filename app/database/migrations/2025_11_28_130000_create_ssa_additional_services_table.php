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
        Schema::create('ssa_additional_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ssa_id')
                ->constrained('service_support_agreements')
                ->cascadeOnDelete();
            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ssa_id', 'service_id']);
        });

        // migrate existing single additional service values into pivot
        if (Schema::hasColumn('service_support_agreements', 'additional_service_id')) {
            $existing = DB::table('service_support_agreements')
                ->select('id as ssa_id', 'additional_service_id as service_id')
                ->whereNotNull('additional_service_id')
                ->get();

            if ($existing->isNotEmpty()) {
                $timestamp = now();
                $rows = $existing->map(static fn($row) => [
                    'ssa_id' => $row->ssa_id,
                    'service_id' => $row->service_id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all();

                DB::table('ssa_additional_services')->insert($rows);
            }
        }

        if (Schema::hasColumn('service_support_agreements', 'additional_service_id')) {
            Schema::table('service_support_agreements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('additional_service_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('service_support_agreements', function (Blueprint $table) {
            if (! Schema::hasColumn('service_support_agreements', 'additional_service_id')) {
                $table->foreignId('additional_service_id')
                    ->nullable()
                    ->after('primary_service_id')
                    ->constrained('services')
                    ->nullOnDelete();
            }
        });

        $existing = DB::table('ssa_additional_services')
            ->select('ssa_id', 'service_id')
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('ssa_id')
            ->map(fn($group) => $group->first()->service_id);

        foreach ($existing as $ssaId => $serviceId) {
            DB::table('service_support_agreements')
                ->where('id', $ssaId)
                ->update(['additional_service_id' => $serviceId]);
        }

        Schema::dropIfExists('ssa_additional_services');
    }
};
