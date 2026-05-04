<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Display the change password form.
     */
    public function edit(): View
    {
        return view('password.edit');
    }

    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->validated('password')),
            'password_change_prompted_at' => now(),
        ]);

        return redirect()->route('password.edit')->with('status', 'password-updated');
    }
}
