<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Makeup\Services;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\Enums\ScheduleMakeupEmailLogStatus;
use App\Enums\ScheduleMakeupEmailLogType;
use App\Enums\ServiceFrequency;
use App\Mail\ScheduleMakeupReminderMail;
use App\Models\ScheduleMakeupRequest;
use App\Models\ScheduleMakeupRequestEmailLog;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one parent reminder email per batch of pending make-up requests due
 * for reminder. A batch is every row sharing `batch_number` — one email covers
 * every missed session for the same (calendar event, student, therapist).
 *
 * For each batch:
 *  - resolve parent recipient (StudentProfile.schedule_email, falling back
 *    to parent_guardian_email)
 *  - resolve frequency from the schedule's SSA (drives view variant)
 *  - write a queued email log row
 *  - send via Mail::send
 *  - on success: flip every row in the batch to `sent`, stamp `reminder_sent_at`,
 *    flip email log to `sent`
 *  - on failure: flip every row in the batch to `failed`, flip log to `failed`,
 *    record error message. Continue to the next batch.
 *
 * Skipped batches (no parent email on file, no SSA, etc.) are logged but do
 * NOT flip the row to `failed` — they stay `pending` so the next run can
 * retry once the missing data is added.
 */
final class ScheduleMakeupReminderSender
{
    public function __construct(
        private readonly ScheduleMakeupRequestRepositoryInterface $repository,
    ) {}

    /**
     * @return array{batches_total: int, batches_sent: int, batches_skipped: int, batches_failed: int}
     */
    public function sendDue(?CarbonImmutable $on = null): array
    {
        $clock = $on ?? CarbonImmutable::now();
        $batches = $this->repository->listPendingDueBatches($clock->startOfDay());

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $batches->each(function (EloquentCollection $batch, string $batchNumber) use ($clock, &$sent, &$skipped, &$failed): void {
            $outcome = $this->sendBatch($batchNumber, $batch, $clock);
            match ($outcome) {
                'sent' => $sent++,
                'skipped' => $skipped++,
                'failed' => $failed++,
            };
        });

        return [
            'batches_total' => $batches->count(),
            'batches_sent' => $sent,
            'batches_skipped' => $skipped,
            'batches_failed' => $failed,
        ];
    }

    /**
     * @param  EloquentCollection<int, ScheduleMakeupRequest>  $batch
     * @return 'sent'|'skipped'|'failed'
     */
    private function sendBatch(string $batchNumber, EloquentCollection $batch, CarbonImmutable $clock): string
    {
        $head = $batch->first();
        if ($head === null) {
            return 'skipped';
        }

        $recipient = $this->resolveRecipient($head);
        if ($recipient === null) {
            Log::warning('Skipping make-up reminder batch — no parent email on file', [
                'batch_number' => $batchNumber,
                'student_id' => $head->student_id,
            ]);

            return 'skipped';
        }

        $frequency = $this->resolveFrequency($head);
        if ($frequency === null) {
            Log::warning('Skipping make-up reminder batch — no SSA frequency on schedule', [
                'batch_number' => $batchNumber,
                'schedule_id' => $head->schedule_id,
            ]);

            return 'skipped';
        }

        /** @var User $therapist */
        $therapist = $head->therapist;

        $mailable = new ScheduleMakeupReminderMail(
            batch: $batch,
            recipientName: $recipient['name'],
            therapist: $therapist,
            frequency: $frequency,
        );

        $log = $this->logQueued($head, $recipient, $therapist, $frequency);

        try {
            Mail::to($recipient['email'])->send($mailable);
        } catch (Throwable $e) {
            $this->repository->markBatchFailed($batchNumber);
            $log->fill([
                'status' => ScheduleMakeupEmailLogStatus::FAILED->value,
                'failed_at' => $clock->toDateTimeString(),
                'error_message' => $e->getMessage(),
            ])->save();
            Log::error('Make-up reminder email send failed', [
                'batch_number' => $batchNumber,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        $this->repository->markBatchSent($batchNumber, $clock);
        $log->fill([
            'status' => ScheduleMakeupEmailLogStatus::SENT->value,
            'sent_at' => $clock->toDateTimeString(),
        ])->save();

        return 'sent';
    }

    /**
     * @return array{email: string, name: string}|null
     */
    private function resolveRecipient(ScheduleMakeupRequest $request): ?array
    {
        $profile = StudentProfile::query()
            ->where('user_id', $request->student_id)
            ->first();

        if ($profile === null) {
            return null;
        }

        $email = $profile->schedule_email ?? $profile->parent_guardian_email;
        if ($email === null || $email === '') {
            return null;
        }

        return [
            'email' => $email,
            'name' => $profile->parent_guardian_name ?? 'Parent / Guardian',
        ];
    }

    private function resolveFrequency(ScheduleMakeupRequest $request): ?ServiceFrequency
    {
        return $request->schedule?->ssa?->frequency;
    }

    /**
     * @param  array{email: string, name: string}  $recipient
     */
    private function logQueued(
        ScheduleMakeupRequest $head,
        array $recipient,
        User $therapist,
        ServiceFrequency $frequency,
    ): ScheduleMakeupRequestEmailLog {
        /** @var User $student */
        $student = $head->student;

        return ScheduleMakeupRequestEmailLog::query()->create([
            'schedule_makeup_request_id' => $head->id,
            'type' => ScheduleMakeupEmailLogType::REMINDER->value,
            'recipient_email' => $recipient['email'],
            'recipient_name' => $recipient['name'],
            'from_email' => $therapist->email,
            'from_name' => $therapist->name,
            'subject' => "Make-up session needed for {$student->name}",
            'status' => ScheduleMakeupEmailLogStatus::QUEUED->value,
            'metadata' => [
                'batch_number' => $head->batch_number,
                'frequency' => $frequency->value,
            ],
        ]);
    }
}
