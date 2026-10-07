<?php

use App\Actions\ChangeAccountRoleAction;
use App\Enums\AccountHistoryField;
use App\Exceptions\CannotChangeOwnRoleException;
use App\Exceptions\LastActiveAdminException;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('RF-6, RF-7: cambia el rol, deja asiento y el nuevo rol rige en la siguiente solicitud', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();
    $loadedBefore = User::query()->findOrFail($target->id);

    expect($loadedBefore->hasRole('staff'))->toBeTrue();

    $result = app(ChangeAccountRoleAction::class)->handle($target, 'admin', $actor);

    $nextRequest = User::query()->findOrFail($target->id);

    expect($result->roles->pluck('name')->all())->toBe(['admin'])
        ->and($nextRequest->roles->pluck('name')->all())->toBe(['admin']);

    $history = AccountHistory::query()->where('user_id', $target->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::RoleChanged)
        ->and($history->author_id)->toBe($actor->id)
        ->and($history->old_value)->toBe(['role' => 'staff'])
        ->and($history->new_value)->toBe(['role' => 'admin']);
});

it('RF-10: permite degradar a un admin si queda otro admin activo', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();

    app(ChangeAccountRoleAction::class)->handle($target, 'staff', $actor);

    expect($target->refresh()->hasRole('staff'))->toBeTrue();
});

it('RF-11: rechaza que una cuenta cambie su propio rol', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    expect(fn () => app(ChangeAccountRoleAction::class)->handle($admin, 'staff', $admin))
        ->toThrow(CannotChangeOwnRoleException::class);

    expect($admin->refresh()->hasRole('admin'))->toBeTrue()
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-10: rechaza el cambio que dejaría al sistema sin ningún admin activo', function () {
    $actor = User::factory()->staff()->create();
    $onlyAdmin = User::factory()->admin()->create();

    expect(fn () => app(ChangeAccountRoleAction::class)->handle($onlyAdmin, 'staff', $actor))
        ->toThrow(LastActiveAdminException::class);

    expect($onlyAdmin->refresh()->hasRole('admin'))->toBeTrue()
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-10: las cuentas admin inactivas no cuentan como admin activo', function () {
    $actor = User::factory()->staff()->create();
    User::factory()->admin()->inactive()->create();
    $activeAdmin = User::factory()->admin()->create();

    expect(fn () => app(ChangeAccountRoleAction::class)->handle($activeAdmin, 'client', $actor))
        ->toThrow(LastActiveAdminException::class);
});
