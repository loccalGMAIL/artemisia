<?php

use App\Filament\Staff\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
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
