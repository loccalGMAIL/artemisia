<?php

use App\Filament\Staff\Resources\Accounts\AccountResource;
use App\Filament\Staff\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Staff\Resources\Accounts\Pages\ListAccounts;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
});

it('RF-8, RF-9: un staff no ve la sección de cuentas ni puede entrar a ella', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get('/staff')->assertOk()->assertDontSee('/staff/accounts');
    $this->get('/staff/accounts')->assertForbidden();
});

it('RF-2: un admin ve la sección de cuentas y el listado', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($admin);

    $this->get('/staff')->assertOk()->assertSee('/staff/accounts');

    Livewire::test(ListAccounts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$admin, $staff]);
});

it('RF-2, RF-3, RF-5: un admin crea una cuenta con un rol y se envía el enlace', function () {
    Notification::fake();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateAccount::class)
        ->fillForm(['name' => 'Ana Pérez', 'email' => 'ana@example.com', 'role' => 'client'])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'ana@example.com')->sole();

    expect($created->roles->pluck('name')->all())->toBe(['client'])
        ->and($created->refresh()->is_active)->toBeTrue();

    Notification::assertSentTo($created, ResetPassword::class);
});

it('RF-4: el formulario de alta muestra el conflicto con la cuenta existente, incluso inactiva', function () {
    Notification::fake();
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->staff()->inactive()->create(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);

    Livewire::test(CreateAccount::class)
        ->fillForm(['name' => 'Otra Ana', 'email' => 'ANA@Example.com', 'role' => 'staff'])
        ->call('create')
        ->assertHasFormErrors(['email'])
        ->assertSee('Ya existe una cuenta con el email ana@example.com (Ana Pérez).');

    expect(User::query()->count())->toBe(2);

    Notification::assertNothingSent();
});

it('RF-6, RF-35: un admin cambia el rol de otra cuenta desde el listado', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();
    $this->actingAs($admin);

    Livewire::test(ListAccounts::class)
        ->callAction(TestAction::make('changeRole')->table($target), ['role' => 'client'])
        ->assertHasNoFormErrors();

    expect($target->refresh()->hasRole('client'))->toBeTrue()
        ->and(AccountHistory::query()->where('user_id', $target->id)->count())->toBe(1);
});

it('RF-11: no se ofrecen cambiar el propio rol ni desactivar la propia cuenta', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ListAccounts::class)
        ->assertActionHidden(TestAction::make('changeRole')->table($admin))
        ->assertActionHidden(TestAction::make('deactivate')->table($admin));
});

it('RF-32: un admin desactiva y vuelve a activar otra cuenta', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();
    $this->actingAs($admin);

    Livewire::test(ListAccounts::class)
        ->callAction(TestAction::make('deactivate')->table($target));

    expect($target->refresh()->is_active)->toBeFalse();

    Livewire::test(ListAccounts::class)
        ->assertActionHidden(TestAction::make('deactivate')->table($target))
        ->callAction(TestAction::make('activate')->table($target));

    expect($target->refresh()->is_active)->toBeTrue();
});

it('RF-34: la sección no ofrece borrar ni editar cuentas', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();
    $this->actingAs($admin);

    expect(array_keys(AccountResource::getPages()))->toBe(['index', 'create']);

    Livewire::test(ListAccounts::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($target))
        ->assertActionDoesNotExist(TestAction::make('edit')->table($target));
});
