<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill null invoice_id/therapist_bill_id then make columns required.
     */
    public function up(): void
    {
        // Backfill invoice_payments.invoice_id
        $nullInvoicePayments = DB::table('invoice_payments')
            ->whereNull('invoice_id')
            ->get();

        foreach ($nullInvoicePayments as $row) {
            $invoiceId = DB::table('invoice_payment_allocations')
                ->where('invoice_payment_id', $row->id)
                ->value('invoice_id');

            if (! $invoiceId) {
                $invoiceId = DB::table('invoices')
                    ->where('school_id', $row->school_id)
                    ->orderBy('invoice_date')
                    ->orderBy('id')
                    ->value('id');
            }

            if ($invoiceId) {
                DB::table('invoice_payments')
                    ->where('id', $row->id)
                    ->update(['invoice_id' => $invoiceId]);
            }
        }

        // Backfill therapist_bill_payments.therapist_bill_id
        $nullBillPayments = DB::table('therapist_bill_payments')
            ->whereNull('therapist_bill_id')
            ->get();

        foreach ($nullBillPayments as $row) {
            $billId = DB::table('therapist_bill_payment_allocations')
                ->where('therapist_bill_payment_id', $row->id)
                ->value('therapist_bill_id');

            if (! $billId) {
                $billId = DB::table('therapist_bills')
                    ->where('therapist_id', $row->therapist_id)
                    ->orderBy('bill_date')
                    ->orderBy('id')
                    ->value('id');
            }

            if ($billId) {
                DB::table('therapist_bill_payments')
                    ->where('id', $row->id)
                    ->update(['therapist_bill_id' => $billId]);
            }
        }

        // Make invoice_id required (drop FK, delete any remaining nulls, change column, re-add FK)
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });
        DB::table('invoice_payments')->whereNull('invoice_id')->delete();
        DB::statement('ALTER TABLE invoice_payments MODIFY invoice_id BIGINT UNSIGNED NOT NULL');
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // Make therapist_bill_id required
        Schema::table('therapist_bill_payments', function (Blueprint $table) {
            $table->dropForeign(['therapist_bill_id']);
        });
        DB::table('therapist_bill_payments')->whereNull('therapist_bill_id')->delete();
        DB::statement('ALTER TABLE therapist_bill_payments MODIFY therapist_bill_id BIGINT UNSIGNED NOT NULL');
        Schema::table('therapist_bill_payments', function (Blueprint $table) {
            $table->foreign('therapist_bill_id')
                ->references('id')
                ->on('therapist_bills')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });
        DB::statement('ALTER TABLE invoice_payments MODIFY invoice_id BIGINT UNSIGNED NULL');
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::table('therapist_bill_payments', function (Blueprint $table) {
            $table->dropForeign(['therapist_bill_id']);
        });
        DB::statement('ALTER TABLE therapist_bill_payments MODIFY therapist_bill_id BIGINT UNSIGNED NULL');
        Schema::table('therapist_bill_payments', function (Blueprint $table) {
            $table->foreign('therapist_bill_id')
                ->references('id')
                ->on('therapist_bills')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }
};
