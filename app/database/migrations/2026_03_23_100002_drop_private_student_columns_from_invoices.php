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
        // Drop foreign keys if they still exist (MySQL DDL auto-commits, so partial runs may have removed them)
        Schema::table('invoices', function (Blueprint $table) {
            if ($this->foreignKeyExists('invoices', 'invoices_student_id_foreign')) {
                $table->dropForeign(['student_id']);
            }
            if ($this->foreignKeyExists('invoices', 'invoices_parent_id_foreign')) {
                $table->dropForeign(['parent_id']);
            }
        });

        // Drop indexes if they still exist
        Schema::table('invoices', function (Blueprint $table) {
            if ($this->indexExists('invoices', 'invoices_invoice_type_index')) {
                $table->dropIndex(['invoice_type']);
            }
            if ($this->indexExists('invoices', 'invoices_student_id_index')) {
                $table->dropIndex(['student_id']);
            }
            if ($this->indexExists('invoices', 'invoices_parent_id_index')) {
                $table->dropIndex(['parent_id']);
            }
        });

        // Drop columns
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_type',
                'student_id',
                'parent_id',
                'parent_name',
                'parent_email',
                'parent_phone',
                'parent_address',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type', 30)->default('school')->after('billing_mode');
            $table->foreignId('student_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
            $table->string('parent_name')->nullable()->after('school_invoice_email');
            $table->string('parent_email')->nullable()->after('parent_name');
            $table->string('parent_phone')->nullable()->after('parent_email');
            $table->text('parent_address')->nullable()->after('parent_phone');

            $table->index('invoice_type');
            $table->index('student_id');
            $table->index('parent_id');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return count($result) > 0;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $result = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $constraintName]
        );

        return count($result) > 0;
    }
};
