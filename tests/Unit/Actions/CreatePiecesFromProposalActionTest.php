<?php

use App\Actions\CreatePiecesFromProposalAction;
use App\Actions\ProposePiecesFromBudgetAction;
use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Exceptions\BudgetNotAcceptedException;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;
use Illuminate\Support\Collection;

beforeEach(function () {
    app()->setLocale('es');
    $this->author = User::factory()->staff()->create();
    $this->budget = Budget::factory()->accepted()->create();
});

it('RF-3, RF-4, RF-9, RF-10, RF-18, RF-19: crea una pieza pendiente por línea, asociada a su ítem y con su categoría', function () {
    $logo = BudgetItem::factory()->for($this->budget)->create(['name' => 'Logo', 'quantity' => 1]);
    $flyer = BudgetItem::factory()->for($this->budget)->create(['name' => 'Flyer', 'quantity' => 1]);
    $lines = app(ProposePiecesFromBudgetAction::class)->handle($this->budget);

    $pieces = app(CreatePiecesFromProposalAction::class)->handle($this->budget, $lines, $this->author);

    expect($pieces)->toBeInstanceOf(Collection::class)->toHaveCount(2)
        ->and($pieces[0])->toBeInstanceOf(Piece::class)
        ->and($pieces[0]->budget_id)->toBe($this->budget->id)
        ->and($pieces[0]->budget_item_id)->toBe($logo->id)
        ->and($pieces[0]->name)->toBe('Logo')
        ->and($pieces[0]->work_category_id)->toBe($logo->service->work_category_id)
        ->and($pieces[0]->created_by)->toBe($this->author->id)
        ->and($pieces[1]->budget_item_id)->toBe($flyer->id)
        ->and(Piece::query()->where('status', PieceStatus::Pending)->count())->toBe(2)
        ->and($pieces[0]->refresh()->status)->toBe(PieceStatus::Pending)
        ->and($pieces[0]->assignee_id)->toBeNull()
        ->and($pieces[0]->due_date)->toBeNull();
});

it('RF-2: dividir la cantidad de una línea en varias crea varias piezas del mismo ítem', function () {
    $item = BudgetItem::factory()->for($this->budget)->create(['name' => 'Flyer', 'quantity' => 3]);
    $line = app(ProposePiecesFromBudgetAction::class)->handle($this->budget)[0];

    $pieces = app(CreatePiecesFromProposalAction::class)->handle($this->budget, [
        [...$line, 'name' => 'Flyer A', 'quantity' => 2],
        [...$line, 'name' => 'Flyer B', 'quantity' => 1],
    ], $this->author);

    expect($pieces)->toHaveCount(2)
        ->and($pieces->pluck('budget_item_id')->all())->toBe([$item->id, $item->id])
        ->and($pieces->pluck('name')->all())->toBe(['Flyer A', 'Flyer B'])
        ->and(Piece::query()->count())->toBe(2);
});

it('RF-2: las líneas quitadas de la propuesta no generan pieza', function () {
    BudgetItem::factory()->for($this->budget)->create(['name' => 'Logo']);
    BudgetItem::factory()->for($this->budget)->create(['name' => 'Flyer']);
    $lines = app(ProposePiecesFromBudgetAction::class)->handle($this->budget);

    $pieces = app(CreatePiecesFromProposalAction::class)->handle($this->budget, [$lines[1]], $this->author);

    expect($pieces->pluck('name')->all())->toBe(['Flyer'])
        ->and(Piece::query()->count())->toBe(1);
});

it('RF-9: guarda la descripción opcional de la pieza', function () {
    BudgetItem::factory()->for($this->budget)->create();
    $line = app(ProposePiecesFromBudgetAction::class)->handle($this->budget)[0];

    $with = app(CreatePiecesFromProposalAction::class)->handle($this->budget, [[...$line, 'description' => 'Versión horizontal']], $this->author)[0];
    $without = app(CreatePiecesFromProposalAction::class)->handle($this->budget, [$line], $this->author)[0];

    expect($with->description)->toBe('Versión horizontal')
        ->and($without->description)->toBeNull();
});

it('RF-38: registra en el historial el alta de cada pieza con su autor', function () {
    BudgetItem::factory()->count(2)->for($this->budget)->create();
    $lines = app(ProposePiecesFromBudgetAction::class)->handle($this->budget);

    $pieces = app(CreatePiecesFromProposalAction::class)->handle($this->budget, $lines, $this->author);

    $entry = PieceHistory::query()->where('piece_id', $pieces[0]->id)->sole();

    expect(PieceHistory::query()->count())->toBe(2)
        ->and($entry->field)->toBe(PieceHistoryField::Created)
        ->and($entry->old_value)->toBeNull()
        ->and($entry->new_value)->toMatchArray(['name' => $pieces[0]->name, 'status' => 'pending'])
        ->and($entry->author_id)->toBe($this->author->id);
});

it('RF-6: no crea piezas si el presupuesto no está aceptado', function (string $state) {
    $budget = Budget::factory()->{$state}()->create();
    BudgetItem::factory()->for($budget)->create();

    try {
        app(CreatePiecesFromProposalAction::class)->handle($budget, [
            ['budget_item_id' => 1, 'name' => 'Logo', 'quantity' => 1, 'work_category_id' => 1],
        ], $this->author);
        $this->fail('Debió rechazar el presupuesto.');
    } catch (BudgetNotAcceptedException $exception) {
        expect($exception->getMessage())->toContain('aceptado');
    }

    expect(Piece::withTrashed()->count())->toBe(0)
        ->and(PieceHistory::query()->count())->toBe(0);
})->with(['draft', 'sent', 'rejected']);

it('RF-4: confirmar una propuesta vacía no crea nada', function () {
    $pieces = app(CreatePiecesFromProposalAction::class)->handle($this->budget, [], $this->author);

    expect($pieces)->toBeEmpty()
        ->and(Piece::query()->count())->toBe(0);
});
