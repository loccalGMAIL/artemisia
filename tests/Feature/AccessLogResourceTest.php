<?php

use App\Enums\AccountHistoryField;
use App\Filament\Staff\Resources\AccessLogs\AccessLogResource;
use App\Filament\Staff\Resources\AccessLogs\Pages\ListAccessLogs;
use App\Filament\Staff\Resources\AccountHistories\AccountHistoryResource;
use App\Filament\Staff\Resources\AccountHistories\Pages\ListAccountHistories;
use App\Models\AccessLog;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
});

it('RF-38: un admin consulta los registros de acceso', function () {
    $this->actingAs(User::factory()->admin()->create());
    $logs = AccessLog::factory()->count(3)->create();

    $this->get('/staff')->assertOk()->assertSee('/staff/access-logs');

    Livewire::test(ListAccessLogs::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($logs);
});

it('RF-38: un admin consulta el historial de cuentas', function () {
    $this->actingAs(User::factory()->admin()->create());
    $histories = AccountHistory::factory()->count(3)->create();

    $this->get('/staff')->assertOk()->assertSee('/staff/account-histories');

    Livewire::test(ListAccountHistories::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($histories);
});

it('RF-38, RF-39: quien no es admin no ve las secciones ni puede entrar a ellas', function (string $role, string $slug) {
    $this->actingAs(User::factory()->{$role}()->create());

    if ($role !== 'client') {
        $this->get('/staff')->assertOk()->assertDontSee("/staff/{$slug}");
    }

    $this->get("/staff/{$slug}")->assertForbidden();
})->with([
    'staff y registros de acceso' => ['staff', 'access-logs'],
    'staff y historial de cuentas' => ['staff', 'account-histories'],
    'client y registros de acceso' => ['client', 'access-logs'],
    'client y historial de cuentas' => ['client', 'account-histories'],
]);

it('RF-35, RNF-6: las secciones son de solo lectura, sin crear, editar ni borrar', function () {
    $this->actingAs(User::factory()->admin()->create());
    $log = AccessLog::factory()->create();
    $history = AccountHistory::factory()->create();

    expect(array_keys(AccessLogResource::getPages()))->toBe(['index'])
        ->and(array_keys(AccountHistoryResource::getPages()))->toBe(['index'])
        ->and(AccessLogResource::canCreate())->toBeFalse()
        ->and(AccountHistoryResource::canCreate())->toBeFalse();

    Livewire::test(ListAccessLogs::class)
        ->assertActionDoesNotExist(TestAction::make('edit')->table($log))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($log));

    Livewire::test(ListAccountHistories::class)
        ->assertActionDoesNotExist(TestAction::make('edit')->table($history))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($history));
});

it('RF-24: un admin ve en el historial de cuentas los cambios de nombre, email y contraseña con su etiqueta', function () {
    $this->actingAs(User::factory()->admin()->create());
    $account = User::factory()->staff()->create();

    $name = AccountHistory::factory()->for($account)->create([
        'field' => AccountHistoryField::NameChanged,
        'old_value' => ['name' => 'Ana Peres'],
        'new_value' => ['name' => 'Ana Pérez'],
    ]);
    $email = AccountHistory::factory()->for($account)->create([
        'field' => AccountHistoryField::EmailChanged,
        'old_value' => ['email' => 'ana@example.com'],
        'new_value' => ['email' => 'ana.nueva@example.com'],
    ]);
    $password = AccountHistory::factory()->for($account)->create([
        'field' => AccountHistoryField::PasswordChanged,
        'old_value' => null,
        'new_value' => ['changed' => true],
    ]);

    Livewire::test(ListAccountHistories::class)
        ->assertCanSeeTableRecords([$name, $email, $password])
        ->assertTableColumnFormattedStateSet('field', 'Cambio de nombre', $name)
        ->assertTableColumnFormattedStateSet('field', 'Cambio de email', $email)
        ->assertTableColumnFormattedStateSet('field', 'Cambio de contraseña', $password)
        ->assertTableColumnStateSet('new_value', '{"changed":true}', $password);
});

it('RF-24: quien no es admin no puede consultar los cambios de perfil en el historial', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get('/staff/account-histories')->assertForbidden();
})->with(['staff', 'client']);
