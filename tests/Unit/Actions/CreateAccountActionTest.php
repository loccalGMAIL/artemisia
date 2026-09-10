<?php

use App\Actions\CreateAccountAction;
use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('RF-2, RF-3, RF-5, RF-35: crea una cuenta activa con un solo rol, envía el enlace y registra el alta', function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();

    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->actingAs($admin);

    $account = app(CreateAccountAction::class)->handle([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'role' => 'staff',
    ]);

    expect($account)->toBeInstanceOf(User::class)
        ->and($account->is_active)->toBeTrue()
        ->and($account->hasRole('staff'))->toBeTrue()
        ->and($account->roles)->toHaveCount(1);

    Notification::assertSentTo($account, ResetPassword::class);

    $history = AccountHistory::where('user_id', $account->id)->first();

    expect($history)->not->toBeNull()
        ->and($history->field)->toBe(AccountHistoryField::Created)
        ->and($history->author_id)->toBe($admin->id);
});

it('RF-4: rechaza un email ya existente, sin distinguir mayúsculas, espacios exteriores ni cuentas inactivas', function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();

    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->actingAs($admin);

    $existing = User::factory()->create([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'is_active' => false,
    ]);

    $attempt = fn () => app(CreateAccountAction::class)->handle([
        'name' => 'Otra Ana',
        'email' => '  ANA@Example.com  ',
        'role' => 'staff',
    ]);

    expect($attempt)->toThrow(ValidationException::class);

    try {
        $attempt();
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('email')
            ->and($exception->errors()['email'][0])
            ->toBe(__('auth.email_taken', ['name' => $existing->name]));
    }

    expect(User::where('email', '  ANA@Example.com  ')->exists())->toBeFalse()
        ->and(User::count())->toBe(2);
});
