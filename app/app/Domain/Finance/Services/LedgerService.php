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

/**
 * Source-of-truth for ledger writes. Owns the business rules around which
 * transaction types can be created/edited/deleted from where; delegates the
 * chain-walk and locking mechanics to LedgerChainService.
 */
class LedgerService
{
    public function __construct(
        private readonly LedgerChainService $chain = new LedgerChainService,
    ) {}

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
            recordedAt: self::resolveDateOnlyRecordedAt($invoice->invoice_date),
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
        return $this->createEntry(
            ledgerableType: User::class,
            ledgerableId: $bill->therapist_id,
            type: TransactionType::BILL_GENERATED,
            amount: (float) $bill->total_due,
            recordedAt: self::resolveDateOnlyRecordedAt($bill->bill_date),
            referenceType: TherapistBill::class,
            referenceId: $bill->id,
            notes: 'Bill generated: '.$bill->bill_number,
            recordedById: $bill->sent_by_id,
        );
    }

    /**
     * Create an adjustment (credit_note or refund) for a school.
     *
     * Date input is filled in with current time-of-day inside the service —
     * see resolveDateOnlyRecordedAt() and LEDGER_SYSTEM.md "Backdating caveats".
     */
    public function createCreditNoteForSchool(int $schoolId, float $amount, ?string $notes, int $recordedById, CarbonInterface|string $recordedAt): LedgerEntry
    {
        return $this->createAdjustment(School::class, $schoolId, TransactionType::CREDIT_NOTE, $amount, $notes, $recordedById, $recordedAt);
    }

    public function createRefundForSchool(int $schoolId, float $amount, ?string $notes, int $recordedById, CarbonInterface|string $recordedAt): LedgerEntry
    {
        return $this->createAdjustment(School::class, $schoolId, TransactionType::REFUND, $amount, $notes, $recordedById, $recordedAt);
    }

    public function createCreditNoteForTherapist(int $therapistId, float $amount, ?string $notes, int $recordedById, CarbonInterface|string $recordedAt): LedgerEntry
    {
        return $this->createAdjustment(User::class, $therapistId, TransactionType::CREDIT_NOTE, $amount, $notes, $recordedById, $recordedAt);
    }

    public function createRefundForTherapist(int $therapistId, float $amount, ?string $notes, int $recordedById, CarbonInterface|string $recordedAt): LedgerEntry
    {
        return $this->createAdjustment(User::class, $therapistId, TransactionType::REFUND, $amount, $notes, $recordedById, $recordedAt);
    }

    /**
     * @param  class-string  $ledgerableType
     */
    private function createAdjustment(
        string $ledgerableType,
        int $ledgerableId,
        TransactionType $type,
        float $amount,
        ?string $notes,
        int $recordedById,
        CarbonInterface|string $recordedAt,
    ): LedgerEntry {
        return $this->createEntry(
            ledgerableType: $ledgerableType,
            ledgerableId: $ledgerableId,
            type: $type,
            amount: $amount,
            recordedAt: self::resolveDateOnlyRecordedAt($recordedAt),
            referenceType: null,
            referenceId: null,
            notes: $notes,
            recordedById: $recordedById,
        );
    }

    /**
     * Edit an existing credit-note or refund entry. Recomputes the chain
     * unconditionally so any later rows stay correct.
     */
    public function editAdjustment(LedgerEntry $entry, UpdateLedgerAdjustmentDTO $dto): LedgerEntry
    {
        $this->assertIsAdjustment($entry);

        return DB::transaction(function () use ($entry, $dto) {
            $entry->amount = (string) $dto->amount;
            $entry->notes = $dto->notes;
            $entry->recorded_at = self::resolveDateOnlyRecordedAt($dto->recordedAt);
            $entry->save();

            /** @var class-string $ledgerableType */
            $ledgerableType = $entry->ledgerable_type;
            $this->chain->recomputeChain($ledgerableType, (int) $entry->ledgerable_id);

            return $entry->refresh();
        });
    }

    /**
     * Soft-delete a credit-note or refund and recompute the chain.
     * Soft-deleted rows are excluded by Eloquent's SoftDeletes scope and
     * therefore stop contributing to the running balance.
     */
    public function deleteAdjustment(LedgerEntry $entry): void
    {
        $this->assertIsAdjustment($entry);

        DB::transaction(function () use ($entry): void {
            /** @var class-string $ledgerableType */
            $ledgerableType = $entry->ledgerable_type;
            $ledgerableId = (int) $entry->ledgerable_id;

            $entry->delete();

            $this->chain->recomputeChain($ledgerableType, $ledgerableId);
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

    public function getSchoolBalance(int $schoolId): float
    {
        return $this->chain->getCurrentBalance(School::class, $schoolId);
    }

    public function getTherapistBalance(int $therapistId): float
    {
        return $this->chain->getCurrentBalance(User::class, $therapistId);
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
            $latest = $this->chain->lockedLatestEntry($ledgerableType, $ledgerableId);

            $isBackdated = $latest !== null
                && $this->compareKey([$recordedAt, PHP_INT_MAX], [$latest->recorded_at, $latest->id]) <= 0;

            $signed = $amount * $type->balanceDelta();
            $previousBalance = $latest !== null ? (float) $latest->balance_after : 0.0;

            $entry = LedgerEntry::create([
                'ledgerable_type' => $ledgerableType,
                'ledgerable_id' => $ledgerableId,
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_after' => $isBackdated ? 0 : $previousBalance + $signed,
                'recorded_at' => $recordedAt,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'recorded_by_id' => $recordedById,
            ]);

            if ($isBackdated) {
                $this->chain->recomputeChain($ledgerableType, $ledgerableId);

                return $entry->refresh();
            }

            return $entry;
        });
    }

    /**
     * Backwards-compatible alias that forwards to LedgerChainService.
     * Kept so external callers (LedgerVerifyCommand, payment services) keep
     * working without churn while they're migrated to inject the chain
     * service directly.
     *
     * @param  class-string  $ledgerableType
     */
    public function recomputeChainFrom(string $ledgerableType, int $ledgerableId, ?CarbonInterface $from = null): void
    {
        $this->chain->recomputeChainFrom($ledgerableType, $ledgerableId, $from);
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

    /**
     * Combine a date-only source value with the current time-of-day so the
     * resulting recorded_at carries a real timestamp (not 00:00:00 or 23:59:59).
     * The picked date is preserved; only the time portion is filled in from
     * Carbon::now(). A backdated row is therefore stamped with *today's*
     * time-of-day, so it may sort *after* same-day historical rows that were
     * inserted earlier in the day. See LEDGER_SYSTEM.md "Backdating caveats".
     */
    public static function resolveDateOnlyRecordedAt(CarbonInterface|\DateTimeInterface|string|null $source): Carbon
    {
        $now = Carbon::now();

        if ($source === null) {
            return $now;
        }

        return Carbon::parse($source)->setTime($now->hour, $now->minute, $now->second);
    }
}
