<?php

use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Filament\Staff\Resources\Clients\Pages\ViewClient;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
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

it('RF-46, RF-47, RF-48: sin los módulos implementados la ficha no muestra presupuestos, contratos ni pagos', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);

    expect(ClientCardExtensions::registered())->toBe([]);

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Ana Pérez')
        ->assertDontSee('Presupuestos')
        ->assertDontSee('Contratos')
        ->assertDontSee('Pagos');
});

it('RF-46, RF-47, RF-48: un módulo registrado agrega su sección a la ficha con el cliente que se está viendo', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $other = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);

    ClientCardExtensions::register('budgets', fn (Client $record) => Section::make('Presupuestos')->schema([
        TextEntry::make('budgets_owner')->label('Cliente')->state("Presupuestos de {$record->display_name}"),
    ]));

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Presupuestos')
        ->assertSee('Presupuestos de Ana Pérez')
        ->assertDontSee('Luis Gómez');

    expect(ClientCardExtensions::registered())->toBe(['budgets'])
        ->and($other->exists)->toBeTrue();
});

it('RF-46, RF-47, RF-48: registrar dos veces la misma sección la reemplaza y flush la quita', function () {
    $client = Client::factory()->create();

    ClientCardExtensions::register('payments', fn () => Section::make('Pagos viejos')->schema([TextEntry::make('a')->state('a')]));
    ClientCardExtensions::register('payments', fn () => Section::make('Pagos nuevos')->schema([TextEntry::make('b')->state('b')]));

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Pagos nuevos')
        ->assertDontSee('Pagos viejos');

    ClientCardExtensions::flush();

    expect(ClientCardExtensions::registered())->toBe([]);
});
