<?php

use App\Actions\CreatePiecesFromProposalAction;
use App\Actions\ProposePiecesFromBudgetAction;
use App\Actions\RemoveBudgetItemAction;
use App\Actions\RevertBudgetToSentAction;
use App\Actions\UpdateBudgetItemDescriptionAction;
use App\Actions\UpdateBudgetItemQuantityAction;
use App\Enums\PieceStatus;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->budget = Budget::factory()->accepted()->create();
    $this->item = BudgetItem::factory()->for($this->budget)->create(['name' => 'Logo', 'quantity' => 2]);

    $lines = app(ProposePiecesFromBudgetAction::class)->handle($this->budget);
    $this->piece = app(CreatePiecesFromProposalAction::class)->handle($this->budget, $lines, $this->actor)->sole();
    $this->before = $this->piece->refresh()->getAttributes();
    $this->historyCount = PieceHistory::query()->count();
});

function expectPieceUntouched(): void
{
    $piece = Piece::query()->sole();

    expect($piece->getAttributes())->toBe(test()->before)
        ->and($piece->status)->toBe(PieceStatus::Pending)
        ->and(PieceHistory::query()->count())->toBe(test()->historyCount);
}

it('RF-8: las piezas generadas no cambian si el presupuesto vuelve al estado enviado', function () {
    app(RevertBudgetToSentAction::class)->handle($this->budget, $this->actor);

    expect($this->budget->refresh()->status->value)->toBe('sent');
    expectPieceUntouched();
});

it('RF-8: las piezas generadas no cambian si se modifica la cantidad o la descripción del ítem de origen', function () {
    app(RevertBudgetToSentAction::class)->handle($this->budget, $this->actor);

    app(UpdateBudgetItemQuantityAction::class)->handle($this->item, 5, $this->actor);
    app(UpdateBudgetItemDescriptionAction::class)->handle($this->item, 'Otra descripción', $this->actor);

    expect($this->item->refresh()->quantity)->toBe(5);
    expectPieceUntouched();
});

it('RF-8: quitar el ítem de origen no falla y deja la pieza intacta, con su referencia al ítem', function () {
    app(RevertBudgetToSentAction::class)->handle($this->budget, $this->actor);

    app(RemoveBudgetItemAction::class)->handle($this->item, $this->actor);

    expect(BudgetItem::query()->whereKey($this->item->id)->exists())->toBeFalse()
        ->and(Piece::query()->sole()->budgetItem)->toBeNull()
        ->and(Piece::query()->sole()->budget_item_id)->toBe($this->item->id);
    expectPieceUntouched();
});
