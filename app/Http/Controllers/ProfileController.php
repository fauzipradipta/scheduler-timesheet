<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed in user's own details. The employee fields are the ones the
 * timesheet export writes into the template's header block.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'ntt_id' => $user->ntt_id ?? '',
                'project_name' => $user->project_name ?? '',
                'division' => $user->division ?? '',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'ntt_id' => ['required', 'string', 'max:255', Rule::unique('users', 'ntt_id')->ignore($user->id)],
            'project_name' => ['required', 'string', 'max:255'],
            'division' => ['required', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return to_route('profile.edit');
    }

    /**
     * Change the password, which needs the current one so an unattended
     * session cannot lock its owner out.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** The model casts the password, so it is hashed on the way in. */
        $request->user()->update(['password' => $validated['password']]);

        return to_route('profile.edit');
    }
}
