<?php

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Models\AccessLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

function googleProfileFor(string $email): SocialiteUser
{
    return (new SocialiteUser)->map(['id' => 'google-id', 'name' => 'Persona', 'email' => $email]);
}

it('RF-13, RF-14: la ruta de redirección de cada portal envía a Google', function (string $path) {
    Socialite::fake('google');

    $this->get($path)->assertRedirect('https://socialite.fake/google/authorize');
})->with([
    'staff' => '/staff/auth/google/redirect',
    'portal de clientes' => '/portal/auth/google/redirect',
]);

it('RF-13: la pantalla de acceso de cada portal ofrece el ingreso con Google', function (string $login, string $redirectPath) {
    $this->get($login)->assertOk()->assertSee($redirectPath);
})->with([
    'staff' => ['/staff/login', '/staff/auth/google/redirect'],
    'portal de clientes' => ['/portal/login', '/portal/auth/google/redirect'],
]);

it('RF-14: el email de Google que corresponde a una cuenta ingresa y llega a la página inicial de su portal', function (string $role, string $callback, string $home) {
    $user = User::factory()->{$role}()->create(['email' => 'ana@example.com']);
    Socialite::fake('google', googleProfileFor('Ana@Example.com'));

    $this->get($callback)->assertRedirect($home);

    $this->assertAuthenticatedAs($user);

    $log = AccessLog::query()->sole();

    expect($log->method)->toBe(AccessMethod::Google)
        ->and($log->outcome)->toBe(AccessOutcome::Success)
        ->and($log->user_id)->toBe($user->id);
})->with([
    'admin en staff' => ['admin', '/staff/auth/google/callback', '/staff'],
    'staff en staff' => ['staff', '/staff/auth/google/callback', '/staff'],
    'client en portal' => ['client', '/portal/auth/google/callback', '/portal'],
]);

it('RF-17: un email de Google sin cuenta no crea ninguna y se informa que no está habilitada', function (string $callback, string $login) {
    Socialite::fake('google', googleProfileFor('nadie@example.com'));

    $this->get($callback)
        ->assertRedirect($login)
        ->assertSessionHas('login_error', fn (string $message) => str_contains($message, 'no está habilitada') && str_contains($message, 'agencia'));

    $this->assertGuest();

    expect(User::query()->count())->toBe(0);
})->with([
    'staff' => ['/staff/auth/google/callback', '/staff/login'],
    'portal de clientes' => ['/portal/auth/google/callback', '/portal/login'],
]);

it('RF-20, RF-21, RF-22: aplica el mismo orden de evaluación que el ingreso con contraseña', function (string $role, bool $active, string $callback, string $login, string $expected) {
    $factory = User::factory()->{$role}();
    $user = ($active ? $factory : $factory->inactive())->create(['email' => 'ana@example.com']);
    Socialite::fake('google', googleProfileFor($user->email));

    $this->get($callback)
        ->assertRedirect($login)
        ->assertSessionHas('login_error', fn (string $message) => str_contains($message, $expected));

    $this->assertGuest();
})->with([
    'cuenta inactiva' => ['staff', false, '/staff/auth/google/callback', '/staff/login', 'deshabilitada'],
    'client en staff' => ['client', true, '/staff/auth/google/callback', '/staff/login', 'no accede a ese portal'],
    'staff en portal' => ['staff', true, '/portal/auth/google/callback', '/portal/login', 'no accede a ese portal'],
]);

it('RF-15: si el proceso con Google falla vuelve a la pantalla de acceso sin crear ninguna cuenta', function (string $callback, string $login) {
    Socialite::fake('google', fn () => throw new RuntimeException('Google no respondió'));

    $this->get($callback)
        ->assertRedirect($login)
        ->assertSessionHas('login_error');

    $this->assertGuest();

    expect(User::query()->count())->toBe(0);
})->with([
    'staff' => ['/staff/auth/google/callback', '/staff/login'],
    'portal de clientes' => ['/portal/auth/google/callback', '/portal/login'],
]);

it('RF-15: si el usuario cancela en Google vuelve a la pantalla de acceso sin ingresar', function () {
    User::factory()->staff()->create(['email' => 'ana@example.com']);
    Socialite::fake('google', googleProfileFor('ana@example.com'));

    $this->get('/staff/auth/google/callback?error=access_denied')
        ->assertRedirect('/staff/login')
        ->assertSessionHas('login_error');

    $this->assertGuest();
});

it('RF-15, RF-17: la pantalla de acceso muestra el mensaje del ingreso rechazado', function () {
    $this->withSession(['login_error' => 'Esa cuenta no está habilitada.'])
        ->get('/staff/login')
        ->assertOk()
        ->assertSee('Esa cuenta no está habilitada.');
});
