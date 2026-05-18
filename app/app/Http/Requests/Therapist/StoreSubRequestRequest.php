<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Schedule\Sub\Repositories\ScheduleSubRequestRepositoryInterface;
use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreSubRequestRequest extends FormRequest
{
    public function __construct(
        private readonly ScheduleSubRequestRepositoryInterface $subRequestRepository,
    ) {
        parent::__construct();
    }

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

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'invitee_ids' => ['required', 'array', 'min:1'],
            'invitee_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var Schedule|null $schedule */
            $schedule = $this->route('schedule');
            if ($schedule === null) {
                return;
            }

            $inviteeIds = $this->input('invitee_ids');
            if (! is_array($inviteeIds) || empty($inviteeIds)) {
                return;
            }

            /** @var array<int, int> $ids */
            $ids = array_map('intval', $inviteeIds);

            $eligibleIds = $this->subRequestRepository->filterEligibleIds($ids, $schedule);

            $ineligible = array_diff($ids, $eligibleIds);
            if (! empty($ineligible)) {
                $v->errors()->add('invitee_ids', 'One or more selected therapists are not eligible to cover this session.');
            }
        });
    }
}
