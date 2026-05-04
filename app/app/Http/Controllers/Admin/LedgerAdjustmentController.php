<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\LedgerService;
use App\DTOs\CreateLedgerAdjustmentDTO;
use App\DTOs\UpdateLedgerAdjustmentDTO;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ledger\CreateLedgerAdjustmentRequest;
use App\Http\Requests\Admin\Ledger\UpdateLedgerAdjustmentRequest;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LedgerAdjustmentController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function store(CreateLedgerAdjustmentRequest $request, string $type, int $id): JsonResponse
    {
        $this->authorize('createAdjustment', LedgerEntry::class);

        $validated = $request->validated();
        $validated['type'] = $type;
        $validated['account_id'] = $id;

        $dto = CreateLedgerAdjustmentDTO::fromArray($validated);
        /** @var \App\Models\User $admin */
        $admin = $request->user();
        $recordedById = (int) $admin->id;

        try {
            $entry = match ([$dto->type, $dto->transactionType]) {
                ['school', TransactionType::CREDIT_NOTE] => $this->ledgerService->createCreditNoteForSchool(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $dto->recordedAt,
                ),
                ['school', TransactionType::REFUND] => $this->ledgerService->createRefundForSchool(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $dto->recordedAt,
                ),
                ['therapist', TransactionType::CREDIT_NOTE] => $this->ledgerService->createCreditNoteForTherapist(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $dto->recordedAt,
                ),
                ['therapist', TransactionType::REFUND] => $this->ledgerService->createRefundForTherapist(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $dto->recordedAt,
                ),
                default => throw new \InvalidArgumentException('Unsupported adjustment type combination.'),
            };

            $message = $dto->transactionType === TransactionType::CREDIT_NOTE
                ? 'Credit note recorded successfully.'
                : 'Refund recorded successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'entry_id' => $entry->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to create ledger adjustment', [
                'error' => $e->getMessage(),
                'type' => $type,
                'account_id' => $id,
                'transaction_type' => $dto->transactionType->value,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while recording the adjustment.',
            ], 500);
        }
    }

    public function show(LedgerEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry);

        return response()->json([
            'success' => true,
            'entry' => [
                'id' => $entry->id,
                'transaction_type' => $entry->transaction_type->value,
                'amount' => (float) $entry->amount,
                'recorded_at' => $entry->recorded_at->toDateString(),
                'notes' => $entry->notes,
            ],
        ]);
    }

    public function update(UpdateLedgerAdjustmentRequest $request, LedgerEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry);

        $dto = UpdateLedgerAdjustmentDTO::fromArray($request->validated());

        try {
            $this->ledgerService->editAdjustment($entry, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Adjustment updated successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to update ledger adjustment', [
                'error' => $e->getMessage(),
                'entry_id' => $entry->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the adjustment.',
            ], 500);
        }
    }

    public function destroy(LedgerEntry $entry): JsonResponse
    {
        $this->authorize('delete', $entry);

        try {
            $this->ledgerService->deleteAdjustment($entry);

            return response()->json([
                'success' => true,
                'message' => 'Adjustment deleted successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to delete ledger adjustment', [
                'error' => $e->getMessage(),
                'entry_id' => $entry->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the adjustment.',
            ], 500);
        }
    }
}
