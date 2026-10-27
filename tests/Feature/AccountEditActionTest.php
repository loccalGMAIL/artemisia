<?php

use App\Enums\AccountHistoryField;
use App\Filament\Staff\Resources\Accounts\Pages\ListAccounts;
use App\Models\AccountHistory;
use App\Models\User;
use App\Notifications\AccountEmailChanged;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
});

it('RF-11: un admin edita el nombre y el email de otra cuenta desde el listado', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);
    $this->actingAs($admin);

    Livewire::test(ListAccounts::class)
        ->mountAction(TestAction::make('edit')->table($target))
        ->assertActionDataSet(['name' => 'Ana Peres', 'email' => 'ana@example.com'])
        ->callAction(TestAction::make('edit')->table($target), ['name' => 'Ana Pérez', 'email' => 'ana.nueva@example.com'])
        ->assertHasNoFormErrors()
        ->assertNotified(__('accounts.notifications.updated'));

    expect($target->refresh()->name)->toBe('Ana Pérez')
        ->and($target->email)->toBe('ana.nueva@example.com')
        ->and(AccountHistory::query()->where('user_id', $target->id)->pluck('author_id')->unique()->all())->toBe([$admin->id])
        ->and(AccountHistory::query()->where('user_id', $target->id)->pluck('field')->all())
        ->toEqualCanonicalizing([AccountHistoryField::NameChanged, AccountHistoryField::EmailChanged]);

    Notification::assertSentOnDemandTimes(AccountEmailChanged::class, 2);
});

it('RF-12: la acción de editar no se ofrece en la fila de la propia cuenta', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->staff()->create();
    $this->actingAs($admin);

    Livewire::test(ListAccounts::class)
        ->assertActionVisible(TestAction::make('edit')->table($other))
        ->assertActionHidden(TestAction::make('edit')->table($admin));
});

it('RF-13: solo un admin puede editar otra cuenta; un staff recibe el mensaje de permiso insuficiente', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $target = User::factory()->client()->create();

    $this->actingAs($admin);
    Livewire::test(ListAccounts::class)->assertActionVisible(TestAction::make('edit')->table($target));

    $decision = Gate::forUser($staff)->inspect('update', $target);

    expect($decision->denied())->toBeTrue()
        ->and($decision->message())->toBe(__('auth.insufficient_permission'));

    $this->actingAs($staff);
    $this->get('/staff/accounts')->assertForbidden();
});

it('RF-14: el formulario muestra el conflicto con una cuenta existente, incluso inactiva, y no guarda nada', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);
    User::factory()->client()->inactive()->create(['name' => 'Luis Gómez', 'email' => 'luis@example.com']);
    $this->actingAs($admin);

    Livewire::test(ListAccounts::class)
        ->callAction(TestAction::make('edit')->table($target), ['name' => 'Ana Pérez', 'email' => 'LUIS@example.com'])
        ->assertHasFormErrors(['email'])
        ->assertSee('Ya existe una cuenta con el email luis@example.com (Luis Gómez).');

    expect($target->refresh()->email)->toBe('ana@example.com')
        ->and($target->name)->toBe('Ana Peres')
        ->and(AccountHistory::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('RF-4, RF-15: el formulario rechaza un nombre vacío y un email vacío o con formato inválido', function (array $data, array $errors) {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create(['name' => 'Ana Peres', 'email' => 'ana@example.com']);
    $this->actingAs($admin);

    Livewire::test(ListAccounts::class)
        ->callAction(TestAction::make('edit')->table($target), $data)
        ->assertHasFormErrors($errors);

    expect($target->refresh()->name)->toBe('Ana Peres')
        ->and($target->email)->toBe('ana@example.com');
})->with([
    'nombre vacío' => [['name' => '', 'email' => 'ana@example.com'], ['name']],
    'email vacío' => [['name' => 'Ana Pérez', 'email' => ''], ['email']],
    'email sin formato' => [['name' => 'Ana Pérez', 'email' => 'ana.example.com'], ['email']],
]);
