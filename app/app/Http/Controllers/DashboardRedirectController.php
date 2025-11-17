<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $role = $user?->role instanceof Role
            ? $user->role
            : Role::tryFrom((string) ($user->role ?? ''));

        $destination = match ($role) {
            Role::ADMIN => 'admin.dashboard',
            Role::THERAPIST => 'therapist.dashboard',
            default => null,
        };

        abort_if($destination === null, 403);

        return redirect()->route($destination);
    }
}
