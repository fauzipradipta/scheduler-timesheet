<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'ntt_id' => 'NTT000001',
        'project_name' => 'Digisales',
        'division' => 'RDL',
    ]);

    $this->actingAs($this->user);
});

/**
 * @return array{name: string, email: string, ntt_id: string, project_name: string, division: string}
 */
function profileFields(array $overrides = []): array
{
    return [
        'name' => 'Ada King',
        'email' => 'ada.king@example.com',
        'ntt_id' => 'NTT000002',
        'project_name' => 'Payments',
        'division' => 'Engineering',
        ...$overrides,
    ];
}

test('the profile page shows the signed in user\'s details', function () {
    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profile')
            ->where('profile', [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'ntt_id' => 'NTT000001',
                'project_name' => 'Digisales',
                'division' => 'RDL',
            ])
        );
});

test('the profile page needs a signed in user', function () {
    auth()->logout();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('a user can update their details', function () {
    $this->patch(route('profile.update'), profileFields())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($this->user->refresh()->only(['name', 'email', 'ntt_id', 'project_name', 'division']))
        ->toBe(profileFields());
});

test('a user may keep their own email and NTT ID', function () {
    $this->patch(route('profile.update'), profileFields([
        'email' => 'ada@example.com',
        'ntt_id' => 'NTT000001',
    ]))->assertSessionHasNoErrors();
});

test('an email or NTT ID another user holds is rejected', function () {
    User::factory()->create(['email' => 'taken@example.com', 'ntt_id' => 'NTT999999']);

    $this->patch(route('profile.update'), profileFields([
        'email' => 'taken@example.com',
        'ntt_id' => 'NTT999999',
    ]))->assertSessionHasErrors(['email', 'ntt_id']);

    expect($this->user->refresh()->email)->toBe('ada@example.com');
});

test('every detail is required', function () {
    $this->patch(route('profile.update'), [])
        ->assertSessionHasErrors(['name', 'email', 'ntt_id', 'project_name', 'division']);
});

test('a user can change their password with the current one', function () {
    $this->put(route('profile.password.update'), [
        'current_password' => 'password',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    expect(Hash::check('a-new-password', $this->user->refresh()->password))->toBeTrue();
});

test('the password is not changed without the current one', function () {
    $this->put(route('profile.password.update'), [
        'current_password' => 'not-my-password',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
});

test('a new password must be confirmed', function () {
    $this->put(route('profile.password.update'), [
        'current_password' => 'password',
        'password' => 'a-new-password',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');
});
