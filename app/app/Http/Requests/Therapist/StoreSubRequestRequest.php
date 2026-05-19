<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Enums\Role;
use App\Models\Schedule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSubRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->isTherapist()) {
            return false;
        }

        /** @var Schedule|null $schedule */
        $schedule = $this->route('schedule');

        return $schedule !== null && (int) $schedule->therapist_id === (int) $user->id;
    }

    /**
     * Validate shape only. Eligibility ("can this therapist cover this session?")
     * is a domain check and lives in ScheduleSubRequestService::assertAllEligible —
     * we do not duplicate it here.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'invitee_ids' => ['required', 'array', 'min:1'],
            'invitee_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $q) => $q->where('role', Role::THERAPIST->value)->whereNull('deleted_at')
                ),
            ],
        ];
    }
}
