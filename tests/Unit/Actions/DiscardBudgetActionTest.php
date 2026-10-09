<?php

use App\Actions\DiscardBudgetAction;
use App\Exceptions\BudgetNotDiscardableException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
});

it('RF-51, RF-52: descarta un borrador, que deja de mostrarse en el listado normal', function () {
    $budget = Budget::factory()->create();
    $other = Budget::factory()->create();

    app(DiscardBudgetAction::class)->handle($budget, $this->actor);

    expect($budget->refresh()->trashed())->toBeTrue()
        ->and(Budget::query()->pluck('id')->all())->toBe([$other->id])
        ->and(Budget::withTrashed()->count())->toBe(2)
        ->and(Budget::onlyTrashed()->pluck('id')->all())->toBe([$budget->id]);
});

it('RF-51: solo se descarta un presupuesto en borrador', function (string $state) {
    $budget = Budget::factory()->{$state}()->create();

    try {
        app(DiscardBudgetAction::class)->handle($budget, $this->actor);
        $this->fail('Debió rechazar el descarte.');
    } catch (BudgetNotDiscardableException $exception) {
        expect($exception->getMessage())->toContain('borrador');
    }

    expect($budget->refresh()->trashed())->toBeFalse()
        ->and(Budget::query()->whereKey($budget->id)->exists())->toBeTrue();
})->with(['sent', 'accepted', 'rejected']);

it('RF-52, RF-53, RNF-8: descartar conserva el presupuesto, sus ítems y todo su historial', function () {
    $budget = Budget::factory()->create();
    BudgetItem::factory()->for($budget)->count(2)->create();
    BudgetHistory::factory()->for($budget)->count(3)->create();

    app(DiscardBudgetAction::class)->handle($budget, $this->actor);

    $kept = Budget::onlyTrashed()->findOrFail($budget->id);

    expect($kept->items)->toHaveCount(2)
        ->and($kept->histories)->toHaveCount(3)
        ->and(BudgetHistory::query()->where('budget_id', $budget->id)->count())->toBe(3)
        ->and(BudgetItem::query()->where('budget_id', $budget->id)->count())->toBe(2);
});

it('RF-53: el presupuesto descartado sigue físicamente en la base, solo marcado', function () {
    $budget = Budget::factory()->create();

    app(DiscardBudgetAction::class)->handle($budget, $this->actor);

    $row = DB::table('budgets')->where('id', $budget->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->deleted_at)->not->toBeNull();
});

it('RF-51: descartar dos veces el mismo borrador no falla ni cambia nada', function () {
    $budget = Budget::factory()->create();

    app(DiscardBudgetAction::class)->handle($budget, $this->actor);
    $deletedAt = $budget->refresh()->deleted_at;

    app(DiscardBudgetAction::class)->handle($budget, $this->actor);

    expect($budget->refresh()->deleted_at->equalTo($deletedAt))->toBeTrue()
        ->and(Budget::withTrashed()->count())->toBe(1);
});

it('RF-53: un presupuesto descartado conserva su identificador, que no se reutiliza', function () {
    $first = Budget::factory()->create();

    app(DiscardBudgetAction::class)->handle($first, $this->actor);

    $next = Budget::factory()->create();

    expect($next->id)->toBeGreaterThan($first->id);
});
