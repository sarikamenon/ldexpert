<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\UpdateLedgerAdjustmentDTO;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\TherapistBill;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LedgerService
{
    /**
     * Create a ledger entry when an invoice is generated.
     */
    public function createInvoiceGeneratedEntry(Invoice $invoice): ?LedgerEntry
    {
        if (! $invoice->school_id) {
            return null;
        }

        return $this->createEntry(
            ledgerableType: School::class,
            ledgerableId: $invoice->school_id,
            type: TransactionType::INVOICE_GENERATED,
            amount: (float) $invoice->total,
            recordedAt: $this->resolveInvoiceRecordedAt($invoice),
            referenceType: Invoice::class,
            referenceId: $invoice->id,
            notes: 'Invoice generated: '.$invoice->invoice_number,
            recordedById: $invoice->sent_by_id,
        );
    }

    /**
     * Create a ledger entry when a therapist bill is generated.
     */
    public function createBillGeneratedEntry(TherapistBill $bill): LedgerEntry
    {
        $entry = $this->createEntry(
            ledgerableType: User::class,
            ledgerableId: $bill->therapist_id,
            type: TransactionType::BILL_GENERATED,
            amount: (float) $bill->total_due,
            recordedAt: $this->resolveBillRecordedAt($bill),
            referenceType: TherapistBill::class,
            referenceId: $bill->id,
            notes: 'Bill generated: '.$bill->bill_number,
            recordedById: $bill->sent_by_id,
        );

        return $entry;
    }

    /**
     * Create a credit note ledger entry for a school (decreases what they owe).
     */
    public function createCreditNoteForSchool(int $schoolId, float $amount, ?string $notes, int $recordedById, CarbonInterface $recordedAt): LedgerEntry
    {
        return $this->createEntry(
            ledgerableType: School::class,
            ledgerableId: $schoolId,
            type: TransactionType::CREDIT_NOTE,
            amount: $amount,
            recordedAt: $recordedAt,
            referenceType: null,
            referenceId: null,
            notes: $notes,
            recordedById: $recordedById,
        );
    }

    /**
     * Create a refund ledger entry for a school (cash sent back; reverses a prior credit position).
     */
    public function createRefundForSchool(int $schoolId, float $amount, ?string $notes, int $recordedById, CarbonInterface $recordedAt): LedgerEntry
    {
        return $this->createEntry(
            ledgerableType: School::class,
            ledgerableId: $schoolId,
            type: TransactionType::REFUND,
            amount: $amount,
            recordedAt: $recordedAt,
            referenceType: null,
            referenceId: null,
            notes: $notes,
            recordedById: $recordedById,
        );
    }

    /**
     * Create a credit note ledger entry for a therapist (we owe them less).
     */
    public function createCreditNoteForTherapist(int $therapistId, float $amount, ?string $notes, int $recordedById, CarbonInterface $recordedAt): LedgerEntry
    {
        return $this->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapistId,
            type: TransactionType::CREDIT_NOTE,
            amount: $amount,
            recordedAt: $recordedAt,
            referenceType: null,
            referenceId: null,
            notes: $notes,
            recordedById: $recordedById,
        );
    }

    /**
     * Create a refund ledger entry for a therapist (therapist returns money to us).
     */
    public function createRefundForTherapist(int $therapistId, float $amount, ?string $notes, int $recordedById, CarbonInterface $recordedAt): LedgerEntry
    {
        return $this->createEntry(
            ledgerableType: User::class,
            ledgerableId: $therapistId,
            type: TransactionType::REFUND,
            amount: $amount,
            recordedAt: $recordedAt,
            referenceType: null,
            referenceId: null,
            notes: $notes,
            recordedById: $recordedById,
        );
    }

    /**
     * Edit an existing credit-note or refund entry. Recomputes the chain from the
     * earlier of (old recorded_at, new recorded_at) so any later rows stay correct.
     */
    public function editAdjustment(LedgerEntry $entry, UpdateLedgerAdjustmentDTO $dto): LedgerEntry
    {
        $this->assertIsAdjustment($entry);

        return DB::transaction(function () use ($entry, $dto) {
            $oldRecordedAt = $entry->recorded_at;
            $newRecordedAt = self::resolveDateOnlyRecordedAt($dto->recordedAt);

            $entry->amount = (string) $dto->amount;
            $entry->notes = $dto->notes;
            $entry->recorded_at = $newRecordedAt;
            $entry->save();

            // Recompute from the earliest of the two timestamps so every potentially
            // affected row gets rewritten. compareKey() lets us pick the older one
            // even when the timestamps are equal but ids differ.
            $from = $newRecordedAt->lessThan($oldRecordedAt) ? $newRecordedAt : $oldRecordedAt;

            /** @var class-string $ledgerableType */
            $ledgerableType = $entry->ledgerable_type;
            $this->recomputeChainFrom($ledgerableType, (int) $entry->ledgerable_id, $from);

            return $entry->refresh();
        });
    }

    /**
     * Soft-delete a credit-note or refund and recompute the chain from its position.
     * Soft-deleted rows are excluded by Eloquent's SoftDeletes scope and therefore
     * stop contributing to the running balance.
     */
    public function deleteAdjustment(LedgerEntry $entry): void
    {
        $this->assertIsAdjustment($entry);

        DB::transaction(function () use ($entry): void {
            $recordedAt = $entry->recorded_at;
            /** @var class-string $ledgerableType */
            $ledgerableType = $entry->ledgerable_type;
            $ledgerableId = (int) $entry->ledgerable_id;

            $entry->delete();

            $this->recomputeChainFrom($ledgerableType, $ledgerableId, $recordedAt);
        });
    }

    /**
     * Guard: only credit_note and refund rows are mutable from the ledger UI.
     * Other types must be edited via their source-document page.
     */
    private function assertIsAdjustment(LedgerEntry $entry): void
    {
        $type = $entry->transaction_type;
        if ($type !== TransactionType::CREDIT_NOTE && $type !== TransactionType::REFUND) {
            throw new InvalidArgumentException(
                'Only credit notes and refunds can be edited or deleted from the ledger.'
            );
        }
    }

    /**
     * Get ledger balance for a school.
     */
    public function getSchoolBalance(int $schoolId): float
    {
        return $this->getCurrentBalance(School::class, $schoolId);
    }

    /**
     * Get ledger balance for a therapist.
     */
    public function getTherapistBalance(int $therapistId): float
    {
        return $this->getCurrentBalance(User::class, $therapistId);
    }

    /**
     * Insert a ledger entry and walk the chain so balance_after stays consistent
     * even when the new row is backdated. Locks the entire account chain inside
     * a DB transaction; safe under concurrent inserts.
     *
     * @param  class-string  $ledgerableType
     * @param  class-string|null  $referenceType
     */
    public function createEntry(
        string $ledgerableType,
        int $ledgerableId,
        TransactionType $type,
        float $amount,
        CarbonInterface $recordedAt,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notes,
        ?int $recordedById,
    ): LedgerEntry {
        return DB::transaction(function () use ($ledgerableType, $ledgerableId, $type, $amount, $recordedAt, $referenceType, $referenceId, $notes, $recordedById) {
            $latest = $this->lockedLatestEntry($ledgerableType, $ledgerableId);

            $isBackdated = $latest !== null
                && $this->compareKey([$recordedAt, PHP_INT_MAX], [$latest->recorded_at, $latest->id]) <= 0;

            $signed = $amount * $type->balanceDelta();

            if (! $isBackdated) {
                $previousBalance = $latest !== null ? (float) $latest->balance_after : 0.0;

                return LedgerEntry::create([
                    'ledgerable_type' => $ledgerableType,
                    'ledgerable_id' => $ledgerableId,
                    'transaction_type' => $type,
                    'amount' => $amount,
                    'balance_after' => $previousBalance + $signed,
                    'recorded_at' => $recordedAt,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $notes,
                    'recorded_by_id' => $recordedById,
                ]);
            }

            // Backdated insert: create with a placeholder balance, then walk the chain.
            $entry = LedgerEntry::create([
                'ledgerable_type' => $ledgerableType,
                'ledgerable_id' => $ledgerableId,
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_after' => 0,
                'recorded_at' => $recordedAt,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'recorded_by_id' => $recordedById,
            ]);

            $this->recomputeChainFrom($ledgerableType, $ledgerableId, $recordedAt);

            return $entry->refresh();
        });
    }

    /**
     * Walk the entire chain for an account (in (recorded_at, id) order) and
     * rewrite balance_after on every row whose stored value disagrees with the
     * running signed-amount total. Locks the account chain via SELECT ... FOR
     * UPDATE so concurrent writers serialize per-account.
     *
     * Soft-deleted rows are excluded by Eloquent's SoftDeletes scope.
     *
     * The $from parameter is retained for backwards compatibility but no longer
     * gates which rows are eligible to heal — any drift detected anywhere in
     * the chain is corrected. This makes the function self-healing against
     * historical residue from earlier partial recomputes.
     *
     * @param  class-string  $ledgerableType
     */
    public function recomputeChainFrom(string $ledgerableType, int $ledgerableId, CarbonInterface $from): void
    {
        DB::transaction(function () use ($ledgerableType, $ledgerableId): void {
            /** @var \Illuminate\Support\Collection<int, LedgerEntry> $rows */
            $rows = LedgerEntry::query()
                ->where('ledgerable_type', $ledgerableType)
                ->where('ledgerable_id', $ledgerableId)
                ->orderBy('recorded_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $running = 0.0;
            foreach ($rows as $row) {
                $running += $row->signedAmount();

                if ((float) $row->balance_after !== $running) {
                    $row->balance_after = (string) $running;
                    $row->save();
                }
            }
        });
    }

    /**
     * @param  class-string  $ledgerableType
     */
    private function lockedLatestEntry(string $ledgerableType, int $ledgerableId): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->where('ledgerable_type', $ledgerableType)
            ->where('ledgerable_id', $ledgerableId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  class-string  $ledgerableType
     */
    private function getCurrentBalance(string $ledgerableType, int $ledgerableId): float
    {
        $lastEntry = LedgerEntry::query()
            ->where('ledgerable_type', $ledgerableType)
            ->where('ledgerable_id', $ledgerableId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        return $lastEntry !== null ? (float) $lastEntry->balance_after : 0.0;
    }

    /**
     * Compare two (timestamp, id) tuples. Returns -1, 0, or 1.
     *
     * @param  array{0: CarbonInterface, 1: int}  $a
     * @param  array{0: CarbonInterface, 1: int}  $b
     */
    private function compareKey(array $a, array $b): int
    {
        $cmp = $a[0]->getTimestamp() <=> $b[0]->getTimestamp();

        return $cmp !== 0 ? $cmp : ($a[1] <=> $b[1]);
    }

    private function resolveInvoiceRecordedAt(Invoice $invoice): CarbonInterface
    {
        return self::resolveDateOnlyRecordedAt($invoice->invoice_date);
    }

    private function resolveBillRecordedAt(TherapistBill $bill): CarbonInterface
    {
        return self::resolveDateOnlyRecordedAt($bill->bill_date);
    }

    /**
     * Combine a date-only source value with the current time-of-day so the
     * resulting recorded_at carries a real timestamp (not 00:00:00 or 23:59:59).
     * The picked date is preserved; only the time portion is filled in from
     * Carbon::now(), so rows sort deterministically by insertion moment within
     * their date and interleave naturally with same-day rows.
     *
     * @param  \Carbon\CarbonInterface|\DateTimeInterface|string|null  $source
     */
    public static function resolveDateOnlyRecordedAt($source): Carbon
    {
        $now = Carbon::now();

        if ($source === null) {
            return $now;
        }

        return Carbon::parse($source)->setTime($now->hour, $now->minute, $now->second);
    }
}
