<?php

use App\Enums\PieceStatus;
use App\Filament\Staff\Resources\Pieces\Pages\ListPieces;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    $this->actor = User::factory()->staff()->create(['name' => 'Sofía Staff']);
    $this->actingAs($this->actor);
});

function piecesList()
{
    return Livewire::test(ListPieces::class);
}

it('RF-41: el listado muestra nombre, presupuesto, cliente, estado, responsable y fecha de entrega', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $budget = Budget::factory()->accepted()->create(['client_id' => $client->id, 'title' => 'Identidad visual']);
    $owner = User::factory()->admin()->create(['name' => 'Beto Responsable']);
    $piece = Piece::factory()->for($budget)->status(PieceStatus::InReview)->assignedTo($owner)->dueOn('2026-11-20')->create(['name' => 'Logo principal']);

    piecesList()
        ->assertCanSeeTableRecords([$piece])
        ->assertSee('Logo principal')
        ->assertSee("#{$budget->id}")
        ->assertSee('Identidad visual')
        ->assertSee('Ana Pérez')
        ->assertSee('En revisión')
        ->assertSee('Beto Responsable')
        ->assertSee('20/11/2026');
})->with(['admin', 'staff']);

it('RF-27: una pieza descartada no se muestra en el listado', function () {
    $visible = Piece::factory()->create();
    $discarded = Piece::factory()->discarded()->create();

    piecesList()
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$discarded]);
});

it('RF-42: filtra por estado', function () {
    $pending = Piece::factory()->create();
    $approved = Piece::factory()->status(PieceStatus::Approved)->create();

    piecesList()
        ->filterTable('status', PieceStatus::Approved->value)
        ->assertCanSeeTableRecords([$approved])
        ->assertCanNotSeeTableRecords([$pending]);
});

it('RF-42: filtra por responsable, y ofrece solo cuentas admin o staff', function () {
    $owner = User::factory()->staff()->create();
    $other = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $mine = Piece::factory()->assignedTo($owner)->create();
    $theirs = Piece::factory()->assignedTo($other)->create();
    $unassigned = Piece::factory()->create();

    piecesList()
        ->assertTableFilterExists('assignee_id', fn ($filter): bool => array_key_exists($owner->id, $filter->getOptions())
            && array_key_exists($other->id, $filter->getOptions())
            && ! array_key_exists($client->id, $filter->getOptions()))
        ->filterTable('assignee_id', $owner->id)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs, $unassigned]);
});

it('RF-42: filtra por cliente', function () {
    $ana = Client::factory()->create();
    $beto = Client::factory()->create();
    $ofAna = Piece::factory()->for(Budget::factory()->accepted()->create(['client_id' => $ana->id]))->create();
    $ofBeto = Piece::factory()->for(Budget::factory()->accepted()->create(['client_id' => $beto->id]))->create();

    piecesList()
        ->filterTable('client', $ana->id)
        ->assertCanSeeTableRecords([$ofAna])
        ->assertCanNotSeeTableRecords([$ofBeto]);
});

it('RF-13, RF-43: el filtro «mis piezas delegadas» muestra únicamente las piezas delegadas a la cuenta que lo usa', function () {
    $other = User::factory()->staff()->create();
    $mine = Piece::factory()->assignedTo($this->actor)->create();
    $theirs = Piece::factory()->assignedTo($other)->create();
    $unassigned = Piece::factory()->create();

    piecesList()
        ->filterTable('mine', true)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs, $unassigned]);

    $this->actingAs($other);

    piecesList()
        ->filterTable('mine', true)
        ->assertCanSeeTableRecords([$theirs])
        ->assertCanNotSeeTableRecords([$mine, $unassigned]);
});

it('RF-17, RF-44: señala las piezas atrasadas y las filtra', function () {
    $late = Piece::factory()->dueOn('2026-10-10')->create(['name' => 'Pieza vencida']);
    $onTime = Piece::factory()->dueOn('2026-10-20')->create(['name' => 'Pieza en fecha']);
    $noDate = Piece::factory()->create(['name' => 'Pieza sin fecha']);
    $deliveredLate = Piece::factory()->status(PieceStatus::Delivered)->dueOn('2026-10-01')->create(['name' => 'Pieza entregada']);

    piecesList()
        ->assertSee('Atrasada')
        ->assertTableColumnStateSet('overdue', 'Atrasada', $late)
        ->assertTableColumnStateSet('overdue', null, $onTime)
        ->assertTableColumnStateSet('overdue', null, $deliveredLate)
        ->filterTable('overdue', true)
        ->assertCanSeeTableRecords([$late])
        ->assertCanNotSeeTableRecords([$onTime, $noDate, $deliveredLate]);
});

it('RNF-2: el listado carga presupuesto, cliente y responsable de una vez, sin consultas por fila', function () {
    $owner = User::factory()->staff()->create();
    $queriesWith = function (int $pieces) use ($owner): int {
        Piece::query()->forceDelete();
        Piece::factory()->count($pieces)->assignedTo($owner)->dueOn('2026-10-01')->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        piecesList()->assertSee($owner->name)->assertSee('Atrasada', false)->assertSee('#');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $few = $queriesWith(3);
    $many = $queriesWith(25);

    expect($many)->toBeLessThanOrEqual($few + 2);
});
