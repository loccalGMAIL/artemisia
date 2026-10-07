<?php

use App\Actions\ActivateAccountAction;
use App\Actions\DeactivateAccountAction;
use App\Enums\AccountHistoryField;
use App\Exceptions\CannotDeactivateOwnAccountException;
use App\Exceptions\LastActiveAdminException;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('RF-32, RF-35: desactiva una cuenta y deja asiento con autor', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();

    $result = app(DeactivateAccountAction::class)->handle($target, $actor);

    expect($result->is_active)->toBeFalse()
        ->and($target->refresh()->is_active)->toBeFalse();

    $history = AccountHistory::query()->where('user_id', $target->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::Deactivated)
        ->and($history->author_id)->toBe($actor->id)
        ->and($history->old_value)->toBe(['is_active' => true])
        ->and($history->new_value)->toBe(['is_active' => false]);
});

it('RF-32, RF-35: activa una cuenta inactiva y deja asiento con autor', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->inactive()->create();

    $result = app(ActivateAccountAction::class)->handle($target, $actor);

    expect($result->is_active)->toBeTrue()
        ->and($target->refresh()->is_active)->toBeTrue();

    $history = AccountHistory::query()->where('user_id', $target->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::Activated)
        ->and($history->author_id)->toBe($actor->id)
        ->and($history->old_value)->toBe(['is_active' => false])
        ->and($history->new_value)->toBe(['is_active' => true]);
});

it('RF-31: al desactivar invalida el enlace de definición vigente sin usar', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();
    $token = Password::createToken($target);

    expect(Password::tokenExists($target, $token))->toBeTrue();

    app(DeactivateAccountAction::class)->handle($target, $actor);

    expect(Password::tokenExists($target, $token))->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', $target->email)->exists())->toBeFalse();
});

it('RF-11: rechaza que una cuenta desactive la propia', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    expect(fn () => app(DeactivateAccountAction::class)->handle($admin, $admin))
        ->toThrow(CannotDeactivateOwnAccountException::class);

    expect($admin->refresh()->is_active)->toBeTrue()
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-10: rechaza desactivar al último admin activo', function () {
    $actor = User::factory()->staff()->create();
    $onlyAdmin = User::factory()->admin()->create();
    User::factory()->admin()->inactive()->create();

    expect(fn () => app(DeactivateAccountAction::class)->handle($onlyAdmin, $actor))
        ->toThrow(LastActiveAdminException::class);

    expect($onlyAdmin->refresh()->is_active)->toBeTrue()
        ->and(AccountHistory::query()->count())->toBe(0);
});

it('RF-10: permite desactivar a un admin si queda otro admin activo', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();

    app(DeactivateAccountAction::class)->handle($target, $actor);

    expect($target->refresh()->is_active)->toBeFalse();
});
