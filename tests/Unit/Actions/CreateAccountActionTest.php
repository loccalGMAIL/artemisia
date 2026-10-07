<?php

use App\Actions\CreateAccountAction;
use App\Enums\AccountHistoryField;
use App\Exceptions\DuplicateAccountEmailException;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
});

it('RF-2, RF-3, RF-5, RF-35: crea la cuenta activa con un solo rol, envía el enlace y deja asiento', function () {
    $actor = User::factory()->create();

    $user = app(CreateAccountAction::class)->handle([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'role' => 'staff',
    ], $actor);

    $user->refresh();

    expect($user->exists)->toBeTrue()
        ->and($user->name)->toBe('Ana Pérez')
        ->and($user->email)->toBe('ana@example.com')
        ->and($user->is_active)->toBeTrue()
        ->and($user->password)->toBeNull()
        ->and($user->roles->pluck('name')->all())->toBe(['staff']);

    Notification::assertSentTo($user, ResetPassword::class);

    $history = AccountHistory::query()->where('user_id', $user->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::Created)
        ->and($history->author_id)->toBe($actor->id)
        ->and($history->old_value)->toBeNull()
        ->and($history->new_value)->toMatchArray(['role' => 'staff']);
});

it('RF-4: rechaza un email repetido aunque cambie la capitalización, tenga espacios o la cuenta esté inactiva', function (string $email) {
    app()->setLocale('es');

    $actor = User::factory()->create();
    $existing = User::factory()->create([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'is_active' => false,
    ]);
    $exception = null;

    try {
        app(CreateAccountAction::class)->handle([
            'name' => 'Otra Ana',
            'email' => $email,
            'role' => 'staff',
        ], $actor);
    } catch (Throwable $e) {
        $exception = $e;
    }

    expect($exception)->toBeInstanceOf(DuplicateAccountEmailException::class)
        ->and($exception->existingAccount->is($existing))->toBeTrue()
        ->and($exception->getMessage())->toContain('ana@example.com')
        ->and(User::query()->count())->toBe(2)
        ->and(AccountHistory::query()->count())->toBe(0);

    Notification::assertNothingSent();
})->with([
    'mayúsculas' => 'ANA@EXAMPLE.COM',
    'espacios exteriores' => '  ana@example.com  ',
    'mezcla de ambos' => ' Ana@Example.COM ',
]);
