<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use App\Models\SessionLog;
use Carbon\CarbonImmutable;

/**
 * Pure mapping helpers that turn SessionLog/LedgerEntry rows into the array
 * shape consumed by the account view's row transformer. Extracted from
 * SchoolAccountViewService to keep that file under the project's 300-line cap.
 */
final class SchoolAccountRowMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function mapSessionLog(SessionLog $log, string $tz): array
    {
        $amount = (float) $log->school_invoice_amount;

        // Per project rules, session log times are displayed in the therapist's
        // timezone (not the school's). $tz (school TZ) is kept for ledger-entry
        // rows; session charges use the therapist's TZ so times match the
        // session log detail page.
        $therapistTz = $log->displayTimezone();
        $startInTherapistTz = $log->localStart($therapistTz);
        $startTime = $startInTherapistTz->format(config('display.time'));
        $duration = (int) $log->duration_minutes;

        return [
            'date' => CarbonImmutable::instance($startInTherapistTz),
            // Sort key uses UTC start_time so charges and adjustments share a
            // common timeline regardless of which TZ each is displayed in.
            'sort_key' => $log->start_time->format('Y-m-d H:i:s'),
            'source_type' => 'session',
            'source_id' => (int) $log->id,
            'type' => 'charge',
            'type_label' => 'Session Charge',
            'student_id' => $log->student_id,
            'student_name' => $log->student?->name,
            'service_name' => $log->service?->name,
            'therapist_name' => $log->therapist?->name,
            'session_time' => $startTime,
            'session_duration_minutes' => $duration,
            'schedule_id' => $log->schedule_id,
            'description_primary' => self::buildSessionPrimaryLine($log),
            'description_secondary' => $startTime.' · '.$duration.' min',
            'debit' => $amount,
            'credit' => null,
            'reference' => null,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => null,
            'recorded_by' => null,
            'signed_amount' => $amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapLedgerEntry(LedgerEntry $entry, string $tz): array
    {
        $type = $entry->transaction_type;
        $amount = (float) $entry->amount;
        $isDebit = $type === TransactionType::REFUND;
        $notes = $entry->notes;

        // recorded_at is stored in UTC; convert to the school's timezone so the
        // displayed calendar date matches what users in the school's frame of
        // reference experienced.
        $recordedInSchoolTz = CarbonImmutable::instance($entry->recorded_at)->setTimezone($tz);

        return [
            'date' => $recordedInSchoolTz,
            'sort_key' => $entry->recorded_at->format('Y-m-d H:i:s'),
            'source_type' => 'ledger',
            'source_id' => (int) $entry->id,
            'type' => $type->value,
            'type_label' => $type->label(),
            'student_id' => null,
            'student_name' => null,
            'service_name' => null,
            'therapist_name' => null,
            'session_time' => null,
            'session_duration_minutes' => null,
            'schedule_id' => null,
            'description_primary' => $notes !== null && $notes !== '' ? $notes : $type->label(),
            'description_secondary' => null,
            'debit' => $isDebit ? $amount : null,
            'credit' => $isDebit ? null : $amount,
            'reference' => $entry->reference,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'notes' => $notes,
            'recorded_by' => $entry->recordedBy?->name,
            'signed_amount' => $amount * $type->balanceDelta(),
        ];
    }

    private static function buildSessionPrimaryLine(SessionLog $log): string
    {
        $service = $log->service?->name;
        $therapist = $log->therapist?->name;

        if ($service !== null && $service !== '' && $therapist !== null) {
            return $service.' · '.$therapist;
        }
        if ($service !== null && $service !== '') {
            return $service;
        }
        if ($therapist !== null) {
            return $therapist;
        }

        return 'Session';
    }
}
