<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Services;

use App\Models\Setting;

final class CompanyInfoService
{
    /**
     * Company details for invoices/bills. Admin-configured `settings` take
     * precedence; the `config('company.*')` values are the fallback.
     *
     * @return array<string, string|null>
     */
    public function getCompanyInfo(): array
    {
        return [
            'name' => Setting::get('company.name', config('company.name')),
            'address' => Setting::get('company.address', config('company.address')),
            'phone' => Setting::get('company.phone', config('company.phone')),
            'email' => Setting::get('company.email', config('company.email')),
            'tax_id' => Setting::get('company.tax_id', config('company.tax_id')),
        ];
    }
}
