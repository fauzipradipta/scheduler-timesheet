<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/reset-password');
    }

    /**
     * Set the new password on the spot. Nothing here confirms the request came
     * from the address it names, so the request's throttle is what keeps this
     * from being an open door onto any account whose email is known.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->firstOrFail();

        /** The model casts the password, so it is hashed on the way in. */
        $user->forceFill(['password' => $validated['password']])
            ->setRememberToken(Str::random(60));

        $user->save();

        event(new PasswordReset($user));

        return to_route('login')->with('status', 'Your password has been reset. Log in with it below.');
    }
}
