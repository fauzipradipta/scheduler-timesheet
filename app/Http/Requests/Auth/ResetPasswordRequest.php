<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ResetPasswordRequest extends FormRequest
{
    /**
     * This form hands out a new password on the strength of an email address
     * alone, so the throttle is the only thing standing between a guessed
     * address and the account behind it. It is deliberately tighter than login.
     */
    private const MAX_ATTEMPTS = 3;

    private const DECAY_MINUTES = 15;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'We could not find an account with that email.',
        ];
    }

    /**
     * Count the attempt before anything is validated, so a run of guesses at
     * the email field runs out of tries as fast as a run of real ones.
     *
     * @throws ValidationException
     */
    protected function prepareForValidation(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Too many reset attempts. Try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::increment($this->throttleKey(), self::DECAY_MINUTES * 60);
    }

    private function throttleKey(): string
    {
        return 'reset-password|'.Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }
}
