<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Domain\Schedule\Sub\Repositories\ScheduleSubRequestRepositoryInterface;
use App\Models\ScheduleSubRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSubRequestInviteesRequest extends FormRequest
{
    public function __construct(
        private readonly ScheduleSubRequestRepositoryInterface $subRequestRepository,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || (! $user->isTherapist() && ! $user->isAdmin())) {
            return false;
        }

        /** @var ScheduleSubRequest|null $subRequest */
        $subRequest = $this->route('subRequest');
        if ($subRequest === null || ! $subRequest->isOpen()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $subRequest->requested_by_id === (int) $user->id;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'invitee_ids' => ['required', 'array', 'min:1'],
            'invitee_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var ScheduleSubRequest|null $subRequest */
            $subRequest = $this->route('subRequest');
            $schedule = $subRequest?->schedule;
            if ($schedule === null) {
                return;
            }

            $inviteeIds = $this->input('invitee_ids');
            if (! is_array($inviteeIds) || empty($inviteeIds)) {
                return;
            }

            /** @var array<int, int> $ids */
            $ids = array_map('intval', $inviteeIds);

            $eligibleIds = $this->subRequestRepository
                ->applyEligibilityFilter(User::query(), $schedule)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

            $ineligible = array_diff($ids, $eligibleIds);
            if (! empty($ineligible)) {
                $v->errors()->add('invitee_ids', 'One or more selected therapists are not eligible to cover this session.');
            }
        });
    }
}
