<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_support_agreements', function (Blueprint $table): void {
            $table->unsignedInteger('calculated_minutes')->nullable()->after('sessions_per_frequency');
            $table->unsignedInteger('adjusted_minutes')->nullable()->after('calculated_minutes');
            $table->text('adjustment_notes')->nullable()->after('adjusted_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('service_support_agreements', function (Blueprint $table): void {
            $table->dropColumn(['calculated_minutes', 'adjusted_minutes', 'adjustment_notes']);
        });
    }
};
