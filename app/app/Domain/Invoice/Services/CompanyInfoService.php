<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Services;

use App\Models\Setting;

final class CompanyInfoService
{
    /**
     * @return array<string, string|null>
     */
    public function getCompanyInfo(): array
    {
        return [
            'name' => Setting::get('company.name', 'The LD Expert, LLC'),
            'address' => Setting::get('company.address', "706 Mesa Ridge\nSan Antonio, TX 78258"),
            'phone' => Setting::get('company.phone', ''),
            'email' => Setting::get('company.email', ''),
            'tax_id' => Setting::get('company.tax_id', null),
        ];
    }
}
