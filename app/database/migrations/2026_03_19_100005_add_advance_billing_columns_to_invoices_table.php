<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_mode', 20)->default('standard')->after('status');
            $table->string('invoice_type', 30)->default('school')->after('billing_mode');
            $table->foreignId('student_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
            $table->decimal('carry_forward_balance', 10, 2)->default(0)->after('total');
            $table->string('parent_name')->nullable()->after('school_invoice_email');
            $table->string('parent_email')->nullable()->after('parent_name');
            $table->string('parent_phone')->nullable()->after('parent_email');
            $table->text('parent_address')->nullable()->after('parent_phone');

            $table->index('billing_mode');
            $table->index('invoice_type');
            $table->index('student_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop foreign keys first (before indexes)
            $table->dropForeign(['student_id']);
            $table->dropForeign(['parent_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Then drop indexes
            $table->dropIndex(['billing_mode']);
            $table->dropIndex(['invoice_type']);
            $table->dropIndex(['student_id']);
            $table->dropIndex(['parent_id']);

            // Finally drop columns
            $table->dropColumn([
                'billing_mode',
                'invoice_type',
                'student_id',
                'parent_id',
                'carry_forward_balance',
                'parent_name',
                'parent_email',
                'parent_phone',
                'parent_address',
            ]);
        });
    }
};
