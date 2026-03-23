<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index();
            $table->string('external_name', 255);
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source', 'external_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_aliases');
    }
};
