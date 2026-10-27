<?php

use App\Actions\UpdateAccountProfileAction;
use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('RF-2, RF-20: guarda el nombre nuevo y deja un asiento con el nombre anterior y el nuevo, el autor y la fecha', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres']);

    $result = app(UpdateAccountProfileAction::class)->handle($target, ['name' => 'Ana Pérez'], $actor);

    expect($result->name)->toBe('Ana Pérez')
        ->and($target->refresh()->name)->toBe('Ana Pérez');

    $history = AccountHistory::query()->where('user_id', $target->id)->sole();

    expect($history->field)->toBe(AccountHistoryField::NameChanged)
        ->and($history->old_value)->toBe(['name' => 'Ana Peres'])
        ->and($history->new_value)->toBe(['name' => 'Ana Pérez'])
        ->and($history->author_id)->toBe($actor->id)
        ->and($history->created_at)->not->toBeNull();
});

it('RF-2, RF-20: una cuenta que cambia su propio nombre es la autora del asiento', function () {
    $account = User::factory()->client()->create(['name' => 'Luis']);

    app(UpdateAccountProfileAction::class)->handle($account, ['name' => 'Luis Gómez'], $account);

    $history = AccountHistory::query()->where('user_id', $account->id)->sole();

    expect($account->refresh()->name)->toBe('Luis Gómez')
        ->and($history->author_id)->toBe($account->id);
});

it('RF-4: rechaza un nombre vacío o de más de 150 caracteres sin cambiar la cuenta', function (string $name) {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Pérez']);

    try {
        app(UpdateAccountProfileAction::class)->handle($target, ['name' => $name], $actor);
        $this->fail('Se esperaba una ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('name');
    }

    expect($target->refresh()->name)->toBe('Ana Pérez')
        ->and(AccountHistory::query()->count())->toBe(0);
})->with([
    'vacío' => [''],
    'solo espacios' => ['   '],
    'más de 150 caracteres' => [str_repeat('a', 151)],
]);

it('RF-23: guardar sin cambios no deja ningún asiento', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres']);
    $action = app(UpdateAccountProfileAction::class);

    $action->handle($target, ['name' => 'Ana Pérez'], $actor);
    $action->handle($target->refresh(), ['name' => 'Ana Pérez'], $actor);
    $action->handle($target->refresh(), ['name' => '  Ana Pérez  '], $actor);

    expect(AccountHistory::query()->where('user_id', $target->id)->count())->toBe(1);
});
