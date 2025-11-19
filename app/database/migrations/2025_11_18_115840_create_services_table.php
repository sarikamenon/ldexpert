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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('direct_service')->default(true);
            $table->boolean('group_service')->default(false);
            $table->string('frequency', 32)->default('adhoc');
            $table->json('delivery_modes')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->unsignedSmallInteger('min_duration_minutes')->nullable();
            $table->unsignedSmallInteger('max_duration_minutes')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
