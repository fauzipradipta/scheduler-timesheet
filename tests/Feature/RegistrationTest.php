<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the register page renders', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/register'));
});

test('a new user can register and lands signed in', function () {
    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'analytical-engine',
        'password_confirmation' => 'analytical-engine',
    ])->assertRedirect(route('attendance.index'));

    $user = User::firstWhere('email', 'ada@example.com');

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and(Hash::check('analytical-engine', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('every field is required', function () {
    $this->post(route('register.store'))
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

test('the password must be confirmed', function () {
    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'analytical-engine',
        'password_confirmation' => 'difference-engine',
    ])->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

test('the password must be at least eight characters', function () {
    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('an email may only be registered once', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'analytical-engine',
        'password_confirmation' => 'analytical-engine',
    ])->assertSessionHasErrors('email');

    expect(User::count())->toBe(1);
});

test('a logged in user is sent away from the register page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect();
});
