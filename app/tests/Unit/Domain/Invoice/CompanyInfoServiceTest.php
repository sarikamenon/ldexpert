<?php

declare(strict_types=1);

use App\Domain\Invoice\Services\CompanyInfoService;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('company info falls back to config when no setting is configured', function () {
    config()->set('company.name', 'Configured Co');
    config()->set('company.address', '1 Config Way');
    config()->set('company.phone', '555-0000');
    config()->set('company.email', 'config@example.com');
    config()->set('company.tax_id', 'TAX-CFG');

    $info = app(CompanyInfoService::class)->getCompanyInfo();

    expect($info['name'])->toBe('Configured Co')
        ->and($info['address'])->toBe('1 Config Way')
        ->and($info['phone'])->toBe('555-0000')
        ->and($info['email'])->toBe('config@example.com')
        ->and($info['tax_id'])->toBe('TAX-CFG');
});

test('admin-configured settings take precedence over config fallback', function () {
    config()->set('company.name', 'Configured Co');

    Setting::set('company.name', 'Settings Co', 'string', 'company');

    $info = app(CompanyInfoService::class)->getCompanyInfo();

    expect($info['name'])->toBe('Settings Co');
});
