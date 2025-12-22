<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSettingsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {}

    public function index(): View
    {
        $settings = $this->settingsService->getAllGroups();

        return view('admin.settings.index', compact('settings'));
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Handle maintenance_mode boolean conversion
        if ($request->has('maintenance_mode')) {
            $validated['maintenance_mode'] = $request->boolean('maintenance_mode');
        }

        $this->settingsService->updateSettings($validated);

        return redirect()->route('admin.settings.index')->with('status', 'Settings updated successfully.');
    }
}
