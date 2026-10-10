<?php

use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Filament\Staff\Resources\Pieces\Pages\ListPieces;
use App\Filament\Staff\Resources\Pieces\Pages\ViewPiece;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->piece = Piece::factory()->create();
});

it('RF-4, RF-11, RF-15, RF-28, RF-36, RF-39: admin y staff operan las piezas desde /staff', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    expect(Gate::getPolicyFor(Piece::class))->not->toBeNull();

    $this->get('/staff/pieces')->assertOk();
    $this->get("/staff/pieces/{$this->piece->id}")->assertOk();

    Livewire::test(ListPieces::class)->assertSuccessful()->assertActionVisible('createLoosePiece');
    Livewire::test(ViewPiece::class, ['record' => $this->piece->getKey()])
        ->assertSuccessful()
        ->assertActionVisible('assignPiece')
        ->assertActionVisible('setDueDate');
})->with(['admin', 'staff']);

it('RF-4: una cuenta client no accede a las piezas del staff', function () {
    $client = User::factory()->client()->create(['client_id' => $this->piece->budget->client_id]);
    $this->actingAs($client);

    $this->get('/staff/pieces')->assertForbidden();
    $this->get("/staff/pieces/{$this->piece->id}")->assertForbidden();

    Livewire::test(ListPieces::class)->assertForbidden();
    Livewire::test(ViewPiece::class, ['record' => $this->piece->getKey()])->assertForbidden();
});

it('RF-4: una cuenta client no puede generar piezas desde un presupuesto', function () {
    $budget = Budget::factory()->accepted()->create();
    $this->actingAs(User::factory()->client()->create(['client_id' => $budget->client_id]));

    expect(Gate::getPolicyFor(Piece::class))->not->toBeNull();

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->assertForbidden();
});

it('RF-4: la acción de generar piezas se ofrece al staff y se rechaza si la cuenta no puede crear piezas', function () {
    $budget = Budget::factory()->accepted()->create();
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->assertActionVisible('generatePieces');

    Gate::before(fn ($user, $ability) => $ability === 'create' ? false : null);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->assertActionHidden('generatePieces');
});
