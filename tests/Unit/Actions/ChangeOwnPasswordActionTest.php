<?php

use App\Actions\ChangeOwnPasswordAction;
use App\Enums\AccountHistoryField;
use App\Exceptions\AccountHasNoPasswordException;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('RF-6, RF-22: cambia la contraseña con la actual correcta y deja un asiento sin ningún dato de contraseña', function () {
    $account = User::factory()->client()->create(['password' => 'clave-actual-1']);

    app(ChangeOwnPasswordAction::class)->handle($account, 'clave-actual-1', 'clave-nueva-2');

    $account->refresh();

    expect(Hash::check('clave-nueva-2', $account->password))->toBeTrue()
        ->and(Hash::check('clave-actual-1', $account->password))->toBeFalse();

    $history = AccountHistory::query()->where('user_id', $account->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::PasswordChanged)
        ->and($history->author_id)->toBe($account->id)
        ->and($history->old_value)->toBeNull()
        ->and($history->new_value)->toBe(['changed' => true])
        ->and($history->created_at)->not->toBeNull()
        ->and(json_encode($history->getAttributes()))->not->toContain('clave-nueva-2')->not->toContain($account->password);
});

it('RF-7: rechaza el cambio si la contraseña actual no coincide', function () {
    $account = User::factory()->staff()->create(['password' => 'clave-actual-1']);

    try {
        app(ChangeOwnPasswordAction::class)->handle($account, 'otra-clave-9', 'clave-nueva-2');
        $this->fail('Se esperaba una ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('currentPassword');
    }

    expect(Hash::check('clave-actual-1', $account->refresh()->password))->toBeTrue()
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-8: rechaza una contraseña nueva de menos de 8 caracteres', function () {
    $account = User::factory()->staff()->create(['password' => 'clave-actual-1']);

    try {
        app(ChangeOwnPasswordAction::class)->handle($account, 'clave-actual-1', 'corta');
        $this->fail('Se esperaba una ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('password');
    }

    expect(Hash::check('clave-actual-1', $account->refresh()->password))->toBeTrue()
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-9: una cuenta sin contraseña definida no puede usar el cambio de contraseña', function () {
    $account = User::factory()->client()->create(['password' => null]);

    expect(fn () => app(ChangeOwnPasswordAction::class)->handle($account, '', 'clave-nueva-2'))
        ->toThrow(AccountHasNoPasswordException::class);

    expect($account->refresh()->password)->toBeNull()
        ->and(AccountHistory::query()->count())->toBe(0);
});
