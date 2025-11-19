<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_contract_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_contract_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('service_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('rate', 10, 2);
            $table->char('rate_type', 1);
            $table->timestamps();

            $table->unique(['school_contract_id', 'service_id'], 'school_contract_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_contract_services');
    }
};
