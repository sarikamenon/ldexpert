<?php

declare(strict_types=1);

namespace App\Http\Requests\Therapist;

use App\Models\ScheduleSubRequest;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSubRequestInviteesRequest extends FormRequest
{
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
            'invitee_ids' => ['required', 'array', 'min:1'],
            'invitee_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
