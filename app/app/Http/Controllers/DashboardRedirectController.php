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

        $destination = match ($user?->role) {
            Role::ADMIN => 'admin.dashboard',
            Role::THERAPIST => 'therapist.dashboard',
            Role::STUDENT => 'student.dashboard',
            default => null,
        };

        abort_if($destination === null, 403);

        return redirect()->route($destination);
    }
}
