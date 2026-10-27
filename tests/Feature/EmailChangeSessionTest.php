<?php

use App\Actions\AttemptLoginAction;
use App\Actions\UpdateAccountProfileAction;
use App\Enums\AccessMethod;
use App\Enums\AccessPortal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Notification::fake();
});

function changeEmailOf(User $account, string $email): void
{
    app(UpdateAccountProfileAction::class)->handle(
        User::query()->findOrFail($account->id),
        ['name' => $account->name, 'email' => $email],
        User::factory()->admin()->create(),
    );
}

function googleAccountFor(string $email): SocialiteUser
{
    return (new SocialiteUser)->map(['id' => 'google-id', 'name' => 'Persona', 'email' => $email]);
}

it('RF-19: la sesión abierta de una cuenta cuyo email cambia sigue abierta', function (string $role, string $path) {
    $account = User::factory()->{$role}()->create(['email' => 'ana@example.com']);

    $this->actingAs($account)->get($path)->assertOk();

    changeEmailOf($account, 'ana.nueva@example.com');

    $this->get($path)->assertOk();

    $this->assertAuthenticatedAs($account);
})->with([
    'staff' => ['staff', '/staff'],
    'client' => ['client', '/portal'],
]);

it('RF-16: tras el cambio, la cuenta ingresa con contraseña por el email nuevo y no por el anterior', function () {
    $account = User::factory()->staff()->create(['email' => 'ana@example.com']);

    changeEmailOf($account, 'ana.nueva@example.com');

    $login = app(AttemptLoginAction::class);

    expect($login->handle(['email' => 'ana.nueva@example.com', 'password' => 'password'], AccessPortal::Staff, AccessMethod::Password)->successful)->toBeTrue()
        ->and($login->handle(['email' => 'ana@example.com', 'password' => 'password'], AccessPortal::Staff, AccessMethod::Password)->successful)->toBeFalse();
});

it('RF-16: tras el cambio, la cuenta ingresa con Google por el email nuevo', function () {
    $account = User::factory()->staff()->create(['email' => 'ana@example.com']);

    changeEmailOf($account, 'ana.nueva@example.com');
    Socialite::fake('google', googleAccountFor('Ana.Nueva@Example.com'));

    $this->get('/staff/auth/google/callback')->assertRedirect('/staff');

    $this->assertAuthenticatedAs($account);
});

it('RF-16: tras el cambio, el email anterior de Google ya no identifica la cuenta', function () {
    $account = User::factory()->staff()->create(['email' => 'ana@example.com']);

    changeEmailOf($account, 'ana.nueva@example.com');
    Socialite::fake('google', googleAccountFor('ana@example.com'));

    $this->get('/staff/auth/google/callback')->assertRedirect('/staff/login');

    $this->assertGuest();
});
