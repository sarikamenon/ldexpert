<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\TherapistBillPayment;
use Illuminate\Database\Eloquent\Builder;

final class TherapistBillPaymentScope extends BaseModelScope
{
    /**
     * @param  Builder<TherapistBillPayment>  $builder
     * @return Builder<TherapistBillPayment>
     */
    public static function forTherapist(Builder $builder, int $therapistId): Builder
    {
        return $builder->where('therapist_id', $therapistId);
    }

    /**
     * @param  Builder<TherapistBillPayment>  $builder
     * @return Builder<TherapistBillPayment>
     */
    public static function forYear(Builder $builder, int $year): Builder
    {
        return $builder->whereYear('paid_at', $year);
    }
}
