<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Services\BillingSettingsService;
use App\DTOs\BillingSettingsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBillingSettingsRequest;
use App\Models\BillingSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class BillingSettingsController extends Controller
{
    public function __construct(
        private readonly BillingSettingsService $settingsService,
    ) {}

    public function edit(): View
    {
        $this->authorize('viewAny', BillingSchedule::class);

        $settings = $this->settingsService->getSettings();

        return view('admin.billing.settings', [
            'settings' => $settings,
        ]);
    }

    public function update(UpdateBillingSettingsRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', BillingSchedule::class);

        $dto = BillingSettingsDTO::fromArray($request->validated());
        $this->settingsService->updateSettings($dto);

        return redirect()
            ->route('admin.billing.settings.edit')
            ->with('success', 'Billing settings updated successfully.');
    }
}
