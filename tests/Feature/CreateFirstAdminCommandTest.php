<?php

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    app()->setLocale('es');
    Notification::fake();
});

it('crea una cuenta admin activa sobre una base vacía y envía el enlace para definir la contraseña', function () {
    expect(User::query()->count())->toBe(0);

    $this->artisan('accounts:create-first-admin', ['name' => 'Ana Pérez', 'email' => 'Ana@Example.com'])
        ->expectsOutputToContain('ana@example.com')
        ->assertSuccessful();

    $admin = User::query()->sole();

    expect($admin->name)->toBe('Ana Pérez')
        ->and($admin->email)->toBe('ana@example.com')
        ->and($admin->refresh()->is_active)->toBeTrue()
        ->and($admin->roles->pluck('name')->all())->toBe(['admin']);

    Notification::assertSentTo($admin, ResetPassword::class);

    $history = AccountHistory::query()->sole();

    expect($history->field)->toBe(AccountHistoryField::Created)
        ->and($history->user_id)->toBe($admin->id)
        ->and($history->author_id)->toBe($admin->id);
});

it('falla con un mensaje claro si ya existe alguna cuenta admin', function (bool $active) {
    $this->seed(RoleSeeder::class);
    $factory = User::factory()->admin();
    ($active ? $factory : $factory->inactive())->create();

    $this->artisan('accounts:create-first-admin', ['name' => 'Otra Persona', 'email' => 'otra@example.com'])
        ->expectsOutputToContain('Ya existe una cuenta admin')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);

    Notification::assertNothingSent();
})->with([
    'admin activa' => true,
    'admin inactiva' => false,
]);

it('falla con un mensaje claro si el email ya pertenece a otra cuenta', function () {
    $this->seed(RoleSeeder::class);
    User::factory()->staff()->create(['name' => 'Luis Gómez', 'email' => 'luis@example.com']);

    $this->artisan('accounts:create-first-admin', ['name' => 'Otro Luis', 'email' => 'LUIS@example.com'])
        ->expectsOutputToContain('Ya existe una cuenta con el email luis@example.com')
        ->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->sole()->hasRole('admin'))->toBeFalse();
});
