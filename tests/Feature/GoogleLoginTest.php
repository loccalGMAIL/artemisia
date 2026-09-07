<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('logs in a known staff user via Google on the admin panel and stores the google_id', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => $user->name,
        'email' => $user->email,
    ]));

    $response = $this->get('http://admin.localhost/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-123');
});

it('rejects a google email that does not match any user, without creating one', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-999',
        'name' => 'Nadie',
        'email' => 'nadie@example.com',
    ]));

    $response = $this->get('http://admin.localhost/auth/google/callback');

    $response->assertRedirect();
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'nadie@example.com']);
});

it('rejects a cliente user trying to log in via Google on the admin subdomain', function () {
    $clienteUser = User::factory()->cliente()->create(['email' => 'cliente@example.com']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-456',
        'name' => $clienteUser->name,
        'email' => $clienteUser->email,
    ]));

    $response = $this->get('http://admin.localhost/auth/google/callback');

    $response->assertRedirect();
    $this->assertGuest();
});

it('rejects a staff user trying to log in via Google on the cliente subdomain', function () {
    $staff = User::factory()->create(['email' => 'staff2@example.com']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-789',
        'name' => $staff->name,
        'email' => $staff->email,
    ]));

    $response = $this->get('http://clientes.localhost/auth/google/callback');

    $response->assertRedirect();
    $this->assertGuest();
});
