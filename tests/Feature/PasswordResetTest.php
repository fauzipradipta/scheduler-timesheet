<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('the reset password page renders', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/reset-password'));
});

test('a password is changed on the spot, with no mail sent', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('login'))->assertSessionHas('status');

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();

    Notification::assertNothingSent();
});

test('the new password can be used to log in', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
    ])->assertRedirect(route('attendance.index'));

    $this->assertAuthenticatedAs($user);
});

test('the old password stops working', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('resetting fires the password reset event', function () {
    Event::fake([PasswordReset::class]);

    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    Event::assertDispatched(PasswordReset::class);
});

test('an unknown email is rejected', function () {
    $this->post(route('password.store'), [
        'email' => 'nobody@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');
});

test('the email and password are required', function () {
    $this->post(route('password.store'))->assertSessionHasErrors(['email', 'password']);
});

test('the new password has to be confirmed', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-different-password',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a password shorter than the default rule is rejected', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a fourth attempt in a row is throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $this->post(route('password.store'), [
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
    }

    $this->post(route('password.store'), [
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('guessing at addresses runs out of tries too', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('password.store'), [
            'email' => 'nobody@example.com',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('password.store'), [
        'email' => 'nobody@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertInvalid(['email' => 'Too many reset attempts.']);
});

test('a logged in user is sent away from the reset page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('password.request'))
        ->assertRedirect(route('attendance.index'));
});
