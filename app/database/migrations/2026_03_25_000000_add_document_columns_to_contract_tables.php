<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapist_contracts', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('notes');
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_mime_type')->nullable()->after('document_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime_type');
        });

        Schema::table('school_contracts', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('notes');
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_mime_type')->nullable()->after('document_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('therapist_contracts', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name', 'document_mime_type', 'document_size']);
        });

        Schema::table('school_contracts', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name', 'document_mime_type', 'document_size']);
        });
    }
};
