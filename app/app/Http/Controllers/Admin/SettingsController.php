<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = [
            'general' => Setting::getGroup('general'),
            'email' => Setting::getGroup('email'),
            'system' => Setting::getGroup('system'),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'records_per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
        ]);

        // General settings
        if (isset($validated['site_name'])) {
            Setting::set('site_name', $validated['site_name'], 'string', 'general');
        }

        if (isset($validated['support_email'])) {
            Setting::set('support_email', $validated['support_email'], 'string', 'general');
        }

        if (isset($validated['records_per_page'])) {
            Setting::set('records_per_page', $validated['records_per_page'], 'integer', 'general');
        }

        Setting::set('maintenance_mode', $request->boolean('maintenance_mode'), 'boolean', 'system');

        // Email settings
        if (isset($validated['smtp_host'])) {
            Setting::set('smtp_host', $validated['smtp_host'], 'string', 'email');
        }

        if (isset($validated['smtp_port'])) {
            Setting::set('smtp_port', $validated['smtp_port'], 'integer', 'email');
        }

        if (isset($validated['smtp_username'])) {
            Setting::set('smtp_username', $validated['smtp_username'], 'string', 'email');
        }

        if ($request->filled('smtp_password')) {
            Setting::set('smtp_password', $validated['smtp_password'], 'string', 'email', true);
        }

        return redirect()->route('admin.settings.index')->with('status', 'Settings updated successfully.');
    }
}

