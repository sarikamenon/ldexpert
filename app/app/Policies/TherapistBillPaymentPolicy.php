<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;

class TherapistBillPaymentPolicy
{
    /**
     * Determine whether the user can view any therapist bill payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can view the therapist bill payment.
     */
    public function view(User $user, TherapistBillPayment $therapistBillPayment): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can record a payment for a therapist bill.
     */
    public function recordPayment(User $user, TherapistBill $therapistBill): bool
    {
        return $user->role === Role::ADMIN;
    }

    /**
     * Determine whether the user can delete the therapist bill payment.
     */
    public function delete(User $user, TherapistBillPayment $therapistBillPayment): bool
    {
        return $user->role === Role::ADMIN;
    }
}
