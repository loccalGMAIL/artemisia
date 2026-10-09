<?php

use App\Filament\Staff\Resources\Budgets\ClientBudgetsSection;
use App\Filament\Staff\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Filament\Staff\Resources\Clients\Pages\ViewClient;
use App\Models\Budget;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->staff()->create());
    ClientCardExtensions::flush();
});

afterEach(function () {
    ClientCardExtensions::flush();
});

it('RF-46 (003): el módulo de presupuestos registra su sección en la ficha del cliente', function () {
    ClientBudgetsSection::register();

    expect(ClientCardExtensions::registered())->toContain('budgets');
});

it('RF-46 (003): la ficha muestra los presupuestos del cliente con identificador, título, total, estado y emisión', function () {
    ClientBudgetsSection::register();

    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $budget = Budget::factory()->sent()->create([
        'client_id' => $client->id,
        'title' => 'Campaña de otoño',
        'issue_date' => '2026-03-15',
        'subtotal' => '1500.00',
        'total' => '1500.00',
    ]);
    Budget::factory()->create(['client_id' => $other->id, 'title' => 'Ajeno al cliente']);

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Presupuestos')
        ->assertSee("#{$budget->id}")
        ->assertSee('Campaña de otoño')
        ->assertSee('Enviado')
        ->assertSee('15/03/2026')
        ->assertSee('1.500,00')
        ->assertDontSee('Ajeno al cliente');
});

it('RF-46 (003): un cliente sin presupuestos lo indica en la ficha', function () {
    ClientBudgetsSection::register();

    $client = Client::factory()->create();

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Presupuestos')
        ->assertSee('Este cliente todavía no tiene presupuestos.');
});

it('RF-26, RF-30 (003): el selector de clientes del presupuesto ofrece solo clientes activos y no archivados', function () {
    $active = Client::factory()->create(['first_name' => 'Activa', 'last_name' => 'Uno']);
    $inactive = Client::factory()->inactive()->create(['first_name' => 'Inactiva', 'last_name' => 'Dos']);
    $archived = Client::factory()->create(['first_name' => 'Archivada', 'last_name' => 'Tres']);
    $archived->delete();

    $ids = Client::availableForBudgets()->pluck('id');

    expect($ids)->toContain($active->id)
        ->not->toContain($inactive->id)
        ->not->toContain($archived->id);

    Livewire::test(CreateBudget::class)
        ->assertFormFieldExists('client_id', function ($field) use ($active, $inactive, $archived): bool {
            $options = $field->getSearchResults('');

            return array_key_exists($active->id, $options)
                && ! array_key_exists($inactive->id, $options)
                && ! array_key_exists($archived->id, $options);
        });
});
