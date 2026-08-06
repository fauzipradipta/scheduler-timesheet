<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

test('the login page renders', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

test('a user can log in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('attendance.index'));

    $this->assertAuthenticatedAs($user);
});

test('logging in sends the user back to where they were headed', function () {
    $user = User::factory()->create();

    $this->get(route('attendance.index'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('attendance.index'));
});

test('a wrong password is rejected', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the email and password are required', function () {
    $this->post(route('login.store'))->assertSessionHasErrors(['email', 'password']);
});

test('a sixth failed attempt is throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'not-the-password',
        ]);
    }

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a successful login clears the throttle', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'not-the-password',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    expect(RateLimiter::attempts(Str::lower($user->email).'|127.0.0.1'))->toBe(0);
});

test('a logged in user is sent away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect();
});

test('logging out ends the session', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('logging out drops the attendance log kept in the session', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['attendance.entries' => [['id' => 'entry-1']]])
        ->post(route('logout'));

    $this->assertGuest();
    expect(session()->has('attendance.entries'))->toBeFalse();
});

test('a guest cannot reach the attendance page', function () {
    $this->get(route('attendance.index'))->assertRedirect(route('login'));
});
