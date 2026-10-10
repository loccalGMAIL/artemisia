<?php

use App\Actions\ProposePiecesFromBudgetAction;
use App\Exceptions\BudgetNotAcceptedException;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Piece;
use App\Models\Service;
use App\Models\WorkCategory;

beforeEach(function () {
    app()->setLocale('es');
});

it('RF-1: propone una línea por ítem con el nombre y la cantidad del ítem', function () {
    $budget = Budget::factory()->accepted()->create();
    $first = BudgetItem::factory()->for($budget)->create(['name' => 'Logo', 'quantity' => 1]);
    $second = BudgetItem::factory()->for($budget)->create(['name' => 'Flyer', 'quantity' => 3]);

    $lines = app(ProposePiecesFromBudgetAction::class)->handle($budget);

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toMatchArray(['budget_item_id' => $first->id, 'name' => 'Logo', 'quantity' => 1])
        ->and($lines[1])->toMatchArray(['budget_item_id' => $second->id, 'name' => 'Flyer', 'quantity' => 3]);
});

it('RF-10: cada línea hereda la categoría de trabajo del servicio del ítem', function () {
    $category = WorkCategory::factory()->create();
    $budget = Budget::factory()->accepted()->create();
    BudgetItem::factory()->for($budget)->for(Service::factory()->for($category, 'category'))->create();

    $lines = app(ProposePiecesFromBudgetAction::class)->handle($budget);

    expect($lines[0]['work_category_id'])->toBe($category->id);
});

it('RF-1, RF-2: la propuesta no persiste ninguna pieza', function () {
    $budget = Budget::factory()->accepted()->create();
    BudgetItem::factory()->count(2)->for($budget)->create();

    app(ProposePiecesFromBudgetAction::class)->handle($budget);

    expect(Piece::withTrashed()->count())->toBe(0);
});

it('RF-1: un presupuesto aceptado sin ítems propone una lista vacía', function () {
    $budget = Budget::factory()->accepted()->create();

    expect(app(ProposePiecesFromBudgetAction::class)->handle($budget))->toBe([]);
});

it('RF-6, RF-7: rechaza con motivo un presupuesto que no está aceptado', function (string $state) {
    $budget = Budget::factory()->{$state}()->create();
    BudgetItem::factory()->for($budget)->create();

    try {
        app(ProposePiecesFromBudgetAction::class)->handle($budget);
        $this->fail('Debió rechazar el presupuesto.');
    } catch (BudgetNotAcceptedException $exception) {
        expect($exception->getMessage())->toContain('aceptado');
    }
})->with(['draft', 'sent', 'rejected']);
